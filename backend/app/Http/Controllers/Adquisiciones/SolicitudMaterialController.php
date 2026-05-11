<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Articulo;
use App\Models\Adq\SolicitudMaterial;
use App\Models\Adq\SolicitudMaterialDet;
use App\Models\Supervisor;
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

        $query = SolicitudMaterial::with(['empleado', 'detalles.articulo'])
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

        return response()->json($query->get());
    }

    // POST /api/adquisiciones/solicitudes
    public function store(Request $request)
    {
        $request->validate([
            'justificacion' => 'nullable|string|max:500',
            'detalles'      => 'required|array|min:1',
            'detalles.*.articulo_id'         => 'required|exists:pgsql.adq.articulo,id',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
        ]);

        $emp = $request->user();
        $esSupervisor = $this->esSupervisor($emp->id_emp);

        $solicitud = SolicitudMaterial::create([
            'id_emp'        => $emp->id_emp,
            'id_depto'      => $emp->id_depto,
            'fecha'         => now()->toDateString(),
            'justificacion' => $request->justificacion,
            'estado'        => $esSupervisor ? 'APROBADO' : 'PENDIENTE',
            'usuario_aprobacion' => $esSupervisor ? $emp->id_emp : null,
            'fecha_aprobacion'   => $esSupervisor ? now() : null,
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
                    DB::table('adq.articulo')
                        ->where('id', $det->articulo_id)
                        ->update([
                            'stock_actual' => DB::raw('GREATEST(0, stock_actual - ' . $autorizada . ')'),
                            'updated_at'   => now(),
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

        return response()->json($solicitud->fresh(['detalles.articulo', 'empleado']));
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
