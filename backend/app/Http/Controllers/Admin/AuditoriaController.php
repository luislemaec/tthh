<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditoriaController extends Controller
{
    // Antes este endpoint solo dejaba pasar ADMINISTRADOR/TALENTO HUMANO, mientras
    // NominaController::auditoria() (misma tabla nom_auditoria_log, sin ningún filtro por
    // módulo) solo dejaba pasar ADMINISTRADOR/TH NOMINA — dos guards distintos sobre los mismos
    // datos. Unificado: ambos aceptan los 3 roles.
    private const ROLES_AUDITORIA = ['ADMINISTRADOR', 'TALENTO HUMANO', 'TH NOMINA'];

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_AUDITORIA);

        $query = DB::table('dbo.nom_auditoria_log')
            ->orderByDesc('created_at');

        if ($request->filled('tabla')) {
            $query->where('tabla', 'ilike', '%' . $request->tabla . '%');
        }
        if ($request->filled('accion')) {
            $query->where('accion', $request->accion);
        }
        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }
        if ($request->filled('descripcion')) {
            $query->where('descripcion', 'ilike', '%' . $request->descripcion . '%');
        }

        // Derivar módulo desde tabla
        if ($request->filled('modulo')) {
            $modulo = strtolower($request->modulo);
            if ($modulo === 'adquisiciones') {
                $query->where('tabla', 'ilike', 'adq.%');
            } elseif ($modulo === 'transportes') {
                $query->where('tabla', 'ilike', '%trans_%');
            } elseif ($modulo === 'tecnologia') {
                $query->where('tabla', 'ilike', 'dbo.ti_%');
            } elseif ($modulo === 'talento') {
                $query->where(function ($q) {
                    $q->where('tabla', 'auth')
                      ->orWhere(function ($q2) {
                          $q2->where('tabla', 'ilike', 'dbo.%')
                             ->where('tabla', 'not ilike', '%trans_%')
                             ->where('tabla', 'not ilike', 'dbo.ti_%');
                      });
                });
            }
        }

        return response()->json($query->paginate($request->get('per_page', 50)));
    }

    // GET /api/admin/auditoria/acciones — lista canónica dinámica de valores `accion` realmente
    // usados en la tabla, para poblar el filtro del frontend. Antes `AuditoriaView.vue` tenía un
    // array hardcodeado de ~25 acciones que quedó desactualizado frente a las ~70+ que existen
    // hoy en el código (faltaban CREAR_VEHICULO, REABRIR, REVISAR_APROBAR, ACTUALIZAR_DETALLE,
    // SUBIR_FIRMADO, etc. — spec 12 §11.1) — el filtro por acción no servía para la mayoría de
    // acciones reales sin que el usuario ya supiera de antemano la cadena exacta.
    public function acciones(Request $request)
    {
        $this->requireRole($request, self::ROLES_AUDITORIA);

        $acciones = DB::table('dbo.nom_auditoria_log')
            ->select('accion')
            ->distinct()
            ->orderBy('accion')
            ->pluck('accion');

        return response()->json($acciones);
    }
}
