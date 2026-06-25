<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Articulo;
use App\Models\Adq\SolicitudMaterial;
use App\Models\Adq\SolicitudMaterialDet;
use App\Models\Supervisor;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SolicitudMaterialController extends Controller
{
    private function esSupervisor($id_emp): bool
    {
        return Supervisor::where('id_supervisor', $id_emp)->exists();
    }

    private function esRol($id_emp, string $rol): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $id_emp)
            ->where('r.descripcion', $rol)
            ->exists();
    }

    private function empleadosDeSupervidor($id_supervisor)
    {
        $deptos = Supervisor::where('id_supervisor', $id_supervisor)->pluck('id_depto');
        return DB::table('dbo.ad_empleado')
            ->whereIn('id_depto', $deptos)
            ->where('estado', 'ACTIVO')
            ->where('id_depto', '!=', 999)
            ->pluck('id_emp');
    }

    // GET /api/adquisiciones/solicitudes
    public function index(Request $request)
    {
        $emp = $request->user();
        $esBienes      = $this->esRol($emp->id_emp, 'BIENES');
        $esAdq         = $this->esRol($emp->id_emp, 'ADQUISICIONES');
        $esSupervisor  = $this->esSupervisor($emp->id_emp);

        $query = SolicitudMaterial::with(['empleado', 'aprobador', 'detalles.articulo'])
            ->orderByDesc('created_at');

        if ($esBienes || $esAdq) {
            // Ve todas
        } elseif ($esSupervisor) {
            $empleados = $this->empleadosDeSupervidor($emp->id_emp);
            $query->whereIn('id_emp', $empleados);
        } else {
            $query->where('id_emp', $emp->id_emp);
        }

        if ($request->estado) $query->where('estado', $request->estado);

        $solicitudes = $query->get();

        $deptos = DB::table('dbo.ad_departamento')->pluck('nombre_depto', 'id_depto');
        $solicitudes->each(function ($s) use ($deptos) {
            $s->nombre_depto_beneficiario = $s->id_depto_beneficiario
                ? ($deptos[$s->id_depto_beneficiario] ?? 'Depto. ' . $s->id_depto_beneficiario)
                : null;
        });

        return response()->json($solicitudes);
    }

    // POST /api/adquisiciones/solicitudes
    public function store(Request $request)
    {
        $request->validate([
            'justificacion'        => 'nullable|string|max:500',
            'id_depto_beneficiario' => 'nullable|integer',
            'detalles'             => 'required|array|min:1',
            'detalles.*.articulo_id'         => 'required|exists:pgsql.adq.articulo,id',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
        ]);

        $emp = $request->user();
        $esSupervisor  = $this->esSupervisor($emp->id_emp);
        $esBienes      = $this->esRol($emp->id_emp, 'BIENES');
        $esAdq         = $this->esRol($emp->id_emp, 'ADQUISICIONES');
        $deptosBen     = $request->id_depto_beneficiario;

        // Auto-aprobado: supervisor propio, o ADQUISICIONES/BIENES pidiendo por otro depto
        $autoAprobar = $esSupervisor || (($esBienes || $esAdq) && $deptosBen);

        $solicitud = SolicitudMaterial::create([
            'id_emp'                => $emp->id_emp,
            'id_depto'              => $emp->id_depto,
            'id_depto_beneficiario' => $deptosBen ?: null,
            'fecha'                 => now()->toDateString(),
            'justificacion'         => $request->justificacion,
            'estado'                => $autoAprobar ? 'APROBADO' : 'PENDIENTE',
            'usuario_aprobacion'    => $autoAprobar ? $emp->id_emp : null,
            'fecha_aprobacion'      => $autoAprobar ? now() : null,
        ]);

        foreach ($request->detalles as $det) {
            SolicitudMaterialDet::create([
                'solicitud_id'       => $solicitud->id,
                'articulo_id'        => $det['articulo_id'],
                'cantidad_solicitada' => $det['cantidad_solicitada'],
            ]);
        }

        return response()->json($solicitud->load('detalles.articulo'), 201);
    }

    // PATCH /api/adquisiciones/solicitudes/{id}/aprobar  (supervisor)
    public function aprobar(Request $request, $id)
    {
        $emp = $request->user();
        if (!$this->esSupervisor($emp->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }

        $solicitud = SolicitudMaterial::with('detalles')->findOrFail($id);
        if ($solicitud->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se pueden aprobar solicitudes PENDIENTES.'], 422);
        }

        DB::transaction(function () use ($solicitud, $request, $emp) {
            // Actualizar cantidades si el supervisor las modificó
            if ($request->filled('detalles')) {
                foreach ($request->detalles as $item) {
                    $cantidad = max(0.01, (float) ($item['cantidad_solicitada'] ?? 0.01));
                    DB::table('adq.solicitud_material_det')
                        ->where('id', $item['det_id'])
                        ->where('solicitud_id', $solicitud->id)
                        ->update(['cantidad_solicitada' => $cantidad]);
                }
            }

            $solicitud->update([
                'estado'             => 'APROBADO',
                'usuario_aprobacion' => $emp->id_emp,
                'fecha_aprobacion'   => now(),
            ]);
        });

        AuditoriaService::log('adq.solicitud_material', $solicitud->id, 'APROBAR',
            ['estado' => 'PENDIENTE'],
            ['estado' => 'APROBADO'],
            $request, "Aprobación de solicitud de material #{$solicitud->id}");

        return response()->json($solicitud->load('detalles.articulo'));
    }

    // PATCH /api/adquisiciones/solicitudes/{id}/negar  (supervisor)
    public function negar(Request $request, $id)
    {
        $emp = $request->user();
        if (!$this->esSupervisor($emp->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }

        $solicitud = SolicitudMaterial::findOrFail($id);
        if ($solicitud->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se pueden negar solicitudes PENDIENTES.'], 422);
        }

        $solicitud->update([
            'estado'              => 'NEGADO',
            'usuario_aprobacion'  => $emp->id_emp,
            'fecha_aprobacion'    => now(),
        ]);

        AuditoriaService::log('adq.solicitud_material', $solicitud->id, 'NEGAR',
            ['estado' => 'PENDIENTE'],
            ['estado' => 'NEGADO'],
            $request, "Negación de solicitud de material #{$solicitud->id}");

        return response()->json($solicitud->load('detalles.articulo'));
    }

    // PATCH /api/adquisiciones/solicitudes/{id}/despachar  (bienes)
    public function despachar(Request $request, $id)
    {
        $emp = $request->user();
        if (!$this->esRol($emp->id_emp, 'BIENES') && !$this->esRol($emp->id_emp, 'ADQUISICIONES')) {
            return response()->json(['message' => 'No tiene permiso para despachar.'], 403);
        }

        $request->validate([
            'detalles'                         => 'required|array|min:1',
            'detalles.*.det_id'                => 'required|integer',
            'detalles.*.cantidad_autorizada'   => 'required|numeric|min:0',
            'observacion_despacho'             => 'nullable|string|max:500',
        ]);

        $solicitud = SolicitudMaterial::with('detalles.articulo')->findOrFail($id);
        if ($solicitud->estado !== 'APROBADO') {
            return response()->json(['message' => 'Solo se pueden despachar solicitudes APROBADAS.'], 422);
        }

        // Validar stock antes de iniciar la transacción
        foreach ($request->detalles as $item) {
            $det = $solicitud->detalles->firstWhere('id', $item['det_id']);
            if (!$det) continue;
            $autorizada = (float)$item['cantidad_autorizada'];
            if ($autorizada <= 0) continue;

            $stockActual = (float) DB::table('adq.articulo')
                ->where('id', $det->articulo_id)
                ->value('stock_actual');

            if ($autorizada > $stockActual) {
                return response()->json([
                    'message' => "Stock insuficiente para \"{$det->articulo->nombre}\": disponible {$stockActual}, ingresado {$autorizada}.",
                ], 422);
            }
        }

        DB::transaction(function () use ($solicitud, $request, $emp) {
            $totalAutorizado = 0;
            $totalSolicitado = 0;

            foreach ($request->detalles as $item) {
                $det = $solicitud->detalles->firstWhere('id', $item['det_id']);
                if (!$det) continue;

                $autorizada = (float)$item['cantidad_autorizada'];
                $det->update(['cantidad_autorizada' => $autorizada]);

                if ($autorizada > 0) {
                    $articulo    = DB::table('adq.articulo')->where('id', $det->articulo_id)->first();
                    $stockAntes  = (float) $articulo->stock_actual;
                    $precioAntes = (float) $articulo->precio_unitario;
                    $nuevoStock  = max(0, $stockAntes - $autorizada);
                    $nuevoPrecio = $nuevoStock == 0 ? 0 : $precioAntes;

                    DB::table('adq.articulo')
                        ->where('id', $det->articulo_id)
                        ->update([
                            'stock_actual'    => $nuevoStock,
                            'precio_unitario' => $nuevoPrecio,
                            'updated_at'      => now(),
                        ]);

                    DB::table('adq.kardex')->insert([
                        'articulo_id'       => $det->articulo_id,
                        'fecha'             => now(),
                        'tipo_movimiento'   => 'EGRESO',
                        'referencia_tipo'   => 'solicitud_material',
                        'referencia_id'     => $solicitud->id,
                        'referencia_det_id' => $det->id,
                        'numero_documento'  => null,
                        'cantidad_entrada'  => 0,
                        'cantidad_salida'   => $autorizada,
                        'stock_antes'       => $stockAntes,
                        'stock_despues'     => $nuevoStock,
                        'precio_antes'      => $precioAntes,
                        'precio_despues'    => $nuevoPrecio,
                        'precio_movimiento' => $precioAntes,
                        'subtotal'          => round($autorizada * $precioAntes, 2),
                        'iva_valor'         => 0,
                        'total_linea'       => round($autorizada * $precioAntes, 2),
                        'valor_saldo'       => round($nuevoStock * $nuevoPrecio, 2),
                        'usuario'           => $emp->id_emp,
                        'observacion'       => 'Solicitud de materiales #' . $solicitud->id,
                        'created_at'        => now(),
                    ]);
                }

                $totalAutorizado += $autorizada;
                $totalSolicitado += (float)$det->cantidad_solicitada;
            }

            $estado = 'NEGADO';
            if ($totalAutorizado > 0) {
                $estado = $totalAutorizado >= $totalSolicitado ? 'DESPACHADO' : 'DESPACHADO PARCIAL';
            }

            $solicitud->update([
                'estado'               => $estado,
                'usuario_despacho'     => $emp->id_emp,
                'fecha_despacho'       => now(),
                'observacion_despacho' => $request->observacion_despacho,
            ]);
        });

        $solicitudFresh = $solicitud->fresh(['detalles.articulo', 'empleado']);

        AuditoriaService::log('adq.solicitud_material', $solicitud->id, 'DESPACHAR',
            ['estado' => 'APROBADO'],
            ['estado' => $solicitudFresh->estado],
            $request, "Despacho de solicitud de material #{$solicitud->id}");

        return response()->json($solicitudFresh);
    }

    // GET /api/adquisiciones/solicitudes/{id}/pdf
    public function pdf(Request $request, $id)
    {
        $solicitud = SolicitudMaterial::with(['empleado', 'detalles.articulo'])->findOrFail($id);

        if (!in_array($solicitud->estado, ['DESPACHADO', 'DESPACHADO PARCIAL'])) {
            return response()->json(['message' => 'Solo solicitudes despachadas tienen PDF.'], 422);
        }

        $nombreInst = DB::table('dbo.d2_configuracion')
            ->whereRaw("LOWER(concepto) = 'nombre_institucion'")
            ->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';

        $logo = file_exists(public_path('logo.png'))
            ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png')))
            : null;

        $despachador = $solicitud->usuario_despacho
            ? DB::table('dbo.ad_empleado')->where('id_emp', $solicitud->usuario_despacho)->first()
            : null;

        $nombreDespachador = $despachador
            ? strtoupper(trim($despachador->apellido_emp . ' ' . $despachador->nombre_emp))
            : '________________________________';
        $cargoDespachador  = $despachador?->cargo_empleado ?? '';

        $nombreSolicitante = strtoupper(trim($solicitud->empleado->apellido_emp . ' ' . $solicitud->empleado->nombre_emp));
        $cargoSolicitante  = $solicitud->empleado->cargo_empleado ?? '';

        $depto = DB::table('dbo.ad_departamento')
            ->where('id_depto', $solicitud->id_depto)
            ->value('nombre_depto') ?? $solicitud->id_depto;

        $fechaDespacho = $solicitud->fecha_despacho
            ? Carbon::parse($solicitud->fecha_despacho)->format('d/m/Y H:i')
            : '—';

        $generadoPor = trim($request->user()->apellido_emp . ' ' . $request->user()->nombre_emp);

        $pdf = Pdf::loadView('reportes.adq_solicitud_material', compact(
            'solicitud', 'logo', 'nombreInst',
            'nombreDespachador', 'cargoDespachador',
            'nombreSolicitante', 'cargoSolicitante',
            'depto', 'fechaDespacho', 'generadoPor'
        ))->setPaper('a4', 'portrait');

        return $pdf->stream("solicitud-materiales-{$solicitud->id}.pdf");
    }

    public function show($id)
    {
        return response()->json(SolicitudMaterial::with(['empleado', 'detalles.articulo'])->findOrFail($id));
    }

    public function destroy(Request $request, $id)
    {
        $emp = $request->user();
        $solicitud = SolicitudMaterial::findOrFail($id);

        if ($solicitud->id_emp !== $emp->id_emp) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        if ($solicitud->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se pueden eliminar solicitudes PENDIENTES.'], 422);
        }

        $solicitud->delete();
        return response()->json(['message' => 'Solicitud eliminada.']);
    }
}
