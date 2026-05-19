<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $tieneAcceso = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $request->user()->id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])
            ->exists();

        if (!$tieneAcceso) {
            return response()->json(['message' => 'Acceso restringido.'], 403);
        }

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
            } elseif ($modulo === 'talento') {
                $query->where(function ($q) {
                    $q->where('tabla', 'ilike', 'dbo.%')
                      ->where('tabla', 'not ilike', '%trans_%');
                });
            }
        }

        return response()->json($query->paginate($request->get('per_page', 50)));
    }
}
