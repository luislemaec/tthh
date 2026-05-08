<?php
namespace App\Http\Controllers;

use App\Models\Transporte\Vehiculo;
use App\Models\Transporte\Mantenimiento;
use App\Models\Transporte\SolicitudMov;
use App\Models\Empleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class TransporteController extends Controller
{
    private function emp(Request $request)
    {
        return $request->user();
    }

    private function esTransporte(Request $request): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $this->emp($request)->id_emp)
            ->where('r.descripcion', 'TRANSPORTE')
            ->exists();
    }

    private function esConductor(Request $request): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $this->emp($request)->id_emp)
            ->where('r.descripcion', 'CONDUCTOR')
            ->exists();
    }

    // ─── VEHÍCULOS ────────────────────────────────────────────────────────────

    public function index()
    {
        return response()->json(Vehiculo::orderBy('placa')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'placa'              => 'required|string|max:10|unique:pgsql.dbo.trans_vehiculo,placa',
            'marca'              => 'required|string|max:50',
            'modelo'             => 'required|string|max:50',
            'anio'               => 'required|integer|min:1990|max:2100',
            'chasis'             => 'nullable|string|max:50',
            'color'              => 'nullable|string|max:30',
            'kilometraje_actual' => 'required|integer|min:0',
        ]);

        $vehiculo = Vehiculo::create($request->only([
            'placa', 'marca', 'modelo', 'anio', 'chasis', 'color', 'kilometraje_actual',
        ]));

        return response()->json($vehiculo, 201);
    }

    public function update(Request $request, $id)
    {
        $vehiculo = Vehiculo::findOrFail($id);

        $request->validate([
            'placa'              => 'required|string|max:10|unique:pgsql.dbo.trans_vehiculo,placa,' . $id,
            'marca'              => 'required|string|max:50',
            'modelo'             => 'required|string|max:50',
            'anio'               => 'required|integer|min:1990|max:2100',
            'chasis'             => 'nullable|string|max:50',
            'color'              => 'nullable|string|max:30',
            'kilometraje_actual' => 'required|integer|min:0',
            'estado'             => 'required|in:ACTIVO,INACTIVO,MANTENIMIENTO',
        ]);

        $vehiculo->update($request->only([
            'placa', 'marca', 'modelo', 'anio', 'chasis', 'color', 'kilometraje_actual', 'estado',
        ]));

        return response()->json($vehiculo);
    }

    // ─── MANTENIMIENTO ────────────────────────────────────────────────────────

    public function indexMtto(Request $request)
    {
        $query = Mantenimiento::with(['vehiculo', 'conductor', 'responsable'])
            ->orderBy('created_at', 'desc');

        if (!$this->esTransporte($request)) {
            $query->where('id_emp_conductor', $this->emp($request)->id_emp);
        }

        return response()->json($query->get());
    }

    public function storeMtto(Request $request)
    {
        $request->validate([
            'vehiculo_id' => 'required|exists:pgsql.dbo.trans_vehiculo,id',
            'tipo'        => 'required|in:PREVENTIVO,CORRECTIVO',
            'descripcion' => 'required|string',
        ]);

        $m = Mantenimiento::create([
            'vehiculo_id'      => $request->vehiculo_id,
            'tipo'             => $request->tipo,
            'descripcion'      => $request->descripcion,
            'id_emp_conductor' => $this->emp($request)->id_emp,
            'estado'           => 'PENDIENTE',
        ]);

        return response()->json($m->load(['vehiculo', 'conductor']), 201);
    }

    public function updateMtto(Request $request, $id)
    {
        $m = Mantenimiento::findOrFail($id);

        $accion = $request->input('accion');

        if ($accion === 'orden') {
            $request->validate([
                'taller'       => 'required|string|max:100',
                'numero_orden' => 'required|string|max:20',
                'fecha_orden'  => 'required|date',
            ]);
            $m->update([
                'taller'                 => $request->taller,
                'numero_orden'           => $request->numero_orden,
                'fecha_orden'            => $request->fecha_orden,
                'observacion_responsable'=> $request->observacion_responsable,
                'id_emp_responsable'     => $this->emp($request)->id_emp,
                'estado'                 => 'ORDEN_GENERADA',
            ]);
        } elseif ($accion === 'en_taller') {
            $m->update(['estado' => 'EN_TALLER']);
        } elseif ($accion === 'finalizar') {
            $request->validate(['fecha_finalizacion' => 'required|date']);
            $m->update([
                'estado'             => 'FINALIZADO',
                'fecha_finalizacion' => $request->fecha_finalizacion,
                'observacion_responsable' => $request->observacion_responsable ?? $m->observacion_responsable,
            ]);
            // Actualizar km si se informó
            if ($request->filled('km_nuevo')) {
                $m->vehiculo->update(['kilometraje_actual' => $request->km_nuevo]);
            }
        }

        return response()->json($m->load(['vehiculo', 'conductor', 'responsable']));
    }

    public function pdfMtto($id)
    {
        $m = Mantenimiento::with(['vehiculo', 'conductor', 'responsable'])->findOrFail($id);
        $logo = $this->logoBase64();

        $pdf = Pdf::loadView('reportes.trans_orden_trabajo', compact('m', 'logo'))
            ->setPaper('letter', 'portrait');

        return $pdf->stream('orden_trabajo_' . $m->numero_orden . '.pdf');
    }

    // ─── MOVILIZACIÓN ─────────────────────────────────────────────────────────

    public function indexMov(Request $request)
    {
        $emp = $this->emp($request);
        $esTransporte = $this->esTransporte($request);
        $esConductor  = $this->esConductor($request);

        $query = SolicitudMov::with(['solicitante', 'vehiculo', 'conductor'])
            ->orderBy('fecha_movilizacion', 'desc');

        if ($esTransporte) {
            // Ve todas
        } elseif ($esConductor) {
            $query->where('id_emp_conductor', $emp->id_emp);
        } else {
            // Empleado autorizado: solo las suyas
            $query->where('id_emp_solicitante', $emp->id_emp);
        }

        return response()->json($query->get());
    }

    public function storeMov(Request $request)
    {
        $emp = $this->emp($request);

        if (!$emp->puede_solicitar_vehiculo && !$this->esTransporte($request)) {
            return response()->json(['message' => 'No tiene permiso para solicitar vehículos.'], 403);
        }

        $request->validate([
            'motivo'            => 'required|string',
            'fecha_movilizacion'=> 'required|date',
            'hora_salida'       => 'required',
            'hora_retorno'      => 'required',
            'lugar_salida'      => 'nullable|string|max:100',
            'lugar_destino'     => 'required|string|max:200',
            'num_personas'      => 'required|integer|min:1',
        ]);

        $s = SolicitudMov::create(array_merge(
            $request->only(['motivo', 'fecha_movilizacion', 'hora_salida', 'hora_retorno',
                            'lugar_salida', 'lugar_destino', 'num_personas']),
            ['id_emp_solicitante' => $emp->id_emp, 'estado' => 'PENDIENTE']
        ));

        return response()->json($s->load('solicitante'), 201);
    }

    public function updateMov(Request $request, $id)
    {
        $s = SolicitudMov::findOrFail($id);
        $accion = $request->input('accion');

        if ($accion === 'aprobar') {
            $request->validate([
                'vehiculo_id'      => 'required|exists:pgsql.dbo.trans_vehiculo,id',
                'id_emp_conductor' => 'required|exists:pgsql.dbo.ad_empleado,id_emp',
            ]);

            // Verificar disponibilidad del vehículo en ese rango
            $conflicto = SolicitudMov::where('vehiculo_id', $request->vehiculo_id)
                ->where('fecha_movilizacion', $s->fecha_movilizacion)
                ->where('estado', 'APROBADO')
                ->where('id', '!=', $id)
                ->where(function ($q) use ($s) {
                    $q->whereBetween('hora_salida', [$s->hora_salida, $s->hora_retorno])
                      ->orWhereBetween('hora_retorno', [$s->hora_salida, $s->hora_retorno]);
                })->exists();

            if ($conflicto) {
                return response()->json(['message' => 'El vehículo ya tiene una asignación en ese horario.'], 422);
            }

            $s->update([
                'estado'           => 'APROBADO',
                'vehiculo_id'      => $request->vehiculo_id,
                'id_emp_conductor' => $request->id_emp_conductor,
                'observacion'      => $request->observacion,
            ]);

        } elseif ($accion === 'negar') {
            $s->update(['estado' => 'NEGADO', 'observacion' => $request->observacion]);

        } elseif ($accion === 'hoja_ruta') {
            $request->validate([
                'km_salida'  => 'required|integer|min:0',
                'km_retorno' => 'required|integer|min:0',
            ]);
            $s->update([
                'estado'               => 'COMPLETADO',
                'km_salida'            => $request->km_salida,
                'km_retorno'           => $request->km_retorno,
                'hoja_ruta_observacion'=> $request->hoja_ruta_observacion,
                'fecha_completado'     => now(),
            ]);
            // Actualizar km del vehículo
            if ($s->vehiculo_id) {
                $s->vehiculo->update(['kilometraje_actual' => $request->km_retorno]);
            }
        }

        return response()->json($s->load(['solicitante', 'vehiculo', 'conductor']));
    }

    public function pdfMov($id)
    {
        $s = SolicitudMov::with(['solicitante', 'vehiculo', 'conductor'])->findOrFail($id);
        $logo = $this->logoBase64();

        $pdf = Pdf::loadView('reportes.trans_orden_movilizacion', compact('s', 'logo'))
            ->setPaper('letter', 'portrait');

        return $pdf->stream('orden_movilizacion_' . $s->id . '.pdf');
    }

    // ─── CONDUCTORES (para selectores en frontend) ───────────────────────────

    public function conductores()
    {
        $ids = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('r.descripcion', 'CONDUCTOR')
            ->pluck('ur.id_emp');

        return response()->json(
            Empleado::whereIn('id_emp', $ids)
                ->where('estado', 'ACTIVO')
                ->orderBy('apellido_emp')
                ->get(['id_emp', 'apellido_emp', 'nombre_emp'])
        );
    }

    private function logoBase64(): ?string
    {
        $path = public_path('logo.png');
        return file_exists($path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($path)) : null;
    }
}
