<?php
namespace App\Http\Controllers;

use App\Models\Adq\Proveedor;
use App\Models\Transporte\Vehiculo;
use App\Models\Transporte\Mantenimiento;
use App\Models\Transporte\MantenimientoActividad;
use App\Models\Transporte\PlanPreventivoDet;
use App\Models\Transporte\TipoMantenimiento;
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
            'numero_motor'       => 'nullable|string|max:50',
            'kilometraje_actual' => 'required|integer|min:0',
        ]);

        $vehiculo = Vehiculo::create($request->only([
            'placa', 'marca', 'modelo', 'anio', 'chasis', 'color', 'numero_motor', 'kilometraje_actual',
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
            'numero_motor'       => 'nullable|string|max:50',
            'kilometraje_actual' => 'required|integer|min:0',
            'estado'             => 'required|in:ACTIVO,INACTIVO,MANTENIMIENTO',
        ]);

        $vehiculo->update($request->only([
            'placa', 'marca', 'modelo', 'anio', 'chasis', 'color', 'numero_motor', 'kilometraje_actual', 'estado',
        ]));

        return response()->json($vehiculo);
    }

    // ─── MANTENIMIENTO ────────────────────────────────────────────────────────

    public function indexMtto(Request $request)
    {
        $query = Mantenimiento::with(['vehiculo', 'conductor', 'responsable', 'actividades', 'tipoMantenimiento', 'tallerRel'])
            ->orderBy('created_at', 'desc');

        if (!$this->esTransporte($request)) {
            $query->where('id_emp_conductor', $this->emp($request)->id_emp);
        }

        return response()->json($query->get());
    }

    public function storeMtto(Request $request)
    {
        $request->validate([
            'vehiculo_id'           => 'required|exists:pgsql.dbo.trans_vehiculo,id',
            'tipo_mantenimiento_id' => 'required|exists:pgsql.dbo.trans_tipo_mantenimiento,id',
            'km_actual'             => 'required|integer|min:0',
            'descripcion'           => 'nullable|string',
            'plan_preventivo_id'    => 'nullable|exists:pgsql.dbo.trans_plan_preventivo_cab,id',
            'actividades_correctivas'              => 'nullable|array',
            'actividades_correctivas.*.actividad'  => 'required_with:actividades_correctivas|string',
        ]);

        $tipo = TipoMantenimiento::findOrFail($request->tipo_mantenimiento_id);

        $esPreventivo = str_contains($tipo->nombre, 'PREVENTIVO');
        $esCorrectivo = str_contains($tipo->nombre, 'CORRECTIVO');

        if ($esPreventivo && !$request->plan_preventivo_id) {
            return response()->json(['message' => 'Debe seleccionar un plan preventivo para este tipo de mantenimiento.'], 422);
        }

        $m = Mantenimiento::create([
            'vehiculo_id'           => $request->vehiculo_id,
            'tipo'                  => $tipo->nombre,
            'tipo_mantenimiento_id' => $request->tipo_mantenimiento_id,
            'km_actual'             => $request->km_actual,
            'descripcion'           => $request->descripcion ?? $tipo->nombre,
            'plan_preventivo_id'    => $request->plan_preventivo_id,
            'id_emp_conductor'      => $this->emp($request)->id_emp,
            'estado'                => 'PENDIENTE',
        ]);

        if ($esPreventivo && $request->plan_preventivo_id) {
            $detalles = PlanPreventivoDet::where('cab_id', $request->plan_preventivo_id)->orderBy('orden')->get();
            foreach ($detalles as $det) {
                MantenimientoActividad::create([
                    'mantenimiento_id' => $m->id,
                    'tipo'             => 'PREVENTIVO',
                    'tipo_actividad'   => $det->tipo_actividad,
                    'actividad'        => $det->actividad,
                    'orden'            => $det->orden,
                ]);
            }
        }

        if ($esCorrectivo && $request->actividades_correctivas) {
            foreach ($request->actividades_correctivas as $i => $act) {
                MantenimientoActividad::create([
                    'mantenimiento_id' => $m->id,
                    'tipo'             => 'CORRECTIVO',
                    'actividad'        => $act['actividad'],
                    'orden'            => $i + 1,
                ]);
            }
        }

        return response()->json($m->load(['vehiculo', 'conductor', 'actividades', 'tipoMantenimiento']), 201);
    }

    public function updateMtto(Request $request, $id)
    {
        $m = Mantenimiento::findOrFail($id);

        $accion = $request->input('accion');

        if ($accion === 'orden') {
            $request->validate([
                'taller_id'   => 'required|exists:pgsql.adq.proveedor,id',
                'fecha_orden' => 'required|date',
            ]);

            $tallerNombre = Proveedor::findOrFail($request->taller_id)->nombre;

            $anio   = date('Y', strtotime($request->fecha_orden));
            $maxNum = Mantenimiento::where('tipo_mantenimiento_id', $m->tipo_mantenimiento_id)
                ->whereNotNull('numero_orden')
                ->whereRaw("EXTRACT(YEAR FROM fecha_orden) = ?", [$anio])
                ->max(DB::raw("CAST(SPLIT_PART(numero_orden, '-', 1) AS INTEGER)"));
            $numero_orden = str_pad(($maxNum ?? 0) + 1, 4, '0', STR_PAD_LEFT) . '-' . $anio;

            $m->update([
                'taller_id'              => $request->taller_id,
                'taller'                 => $tallerNombre,
                'numero_orden'           => $numero_orden,
                'fecha_orden'            => $request->fecha_orden,
                'observacion_responsable'=> $request->observacion_responsable,
                'id_emp_responsable'     => $this->emp($request)->id_emp,
                'estado'                 => 'ORDEN_GENERADA',
            ]);
        } elseif ($accion === 'negar') {
            $m->update([
                'estado'           => 'NEGADO',
                'motivo_negacion'  => $request->motivo_negacion,
                'fecha_negacion'   => now(),
                'usuario_negacion' => $this->emp($request)->id_emp,
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
        $m = Mantenimiento::with(['vehiculo', 'conductor', 'responsable', 'actividades'])->findOrFail($id);
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
                'estado'             => 'APROBADO',
                'vehiculo_id'        => $request->vehiculo_id,
                'id_emp_conductor'   => $request->id_emp_conductor,
                'observacion'        => $request->observacion,
                'id_emp_responsable' => $this->emp($request)->id_emp,
                'fecha_aprobacion'   => now(),
            ]);

        } elseif ($accion === 'negar') {
            $s->update([
                'estado'             => 'NEGADO',
                'observacion'        => $request->observacion,
                'id_emp_responsable' => $this->emp($request)->id_emp,
                'fecha_negacion'     => now(),
            ]);

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
