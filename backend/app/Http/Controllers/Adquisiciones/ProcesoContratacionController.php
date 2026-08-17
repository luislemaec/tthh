<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcesoContratacionController extends Controller
{
    private const ROLES_ADQ = ['ADMINISTRADOR', 'ADQUISICIONES', 'BIENES'];

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        return response()->json(DB::table('adq.proceso_contratacion')->orderBy('id')->get());
    }

    public function activos(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        return response()->json(
            DB::table('adq.proceso_contratacion')->where('activo', true)->orderBy('nombre')->pluck('nombre')
        );
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $request->validate(['nombre' => 'required|string|max:100']);
        $nombre = strtoupper(trim($request->nombre));

        if (DB::table('adq.proceso_contratacion')->whereRaw('UPPER(nombre) = ?', [$nombre])->exists()) {
            return response()->json(['message' => 'Ya existe ese proceso.'], 422);
        }

        $id = DB::table('adq.proceso_contratacion')->insertGetId(['nombre' => $nombre, 'activo' => true]);
        return response()->json(DB::table('adq.proceso_contratacion')->find($id), 201);
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $request->validate(['nombre' => 'required|string|max:100']);
        $nombre = strtoupper(trim($request->nombre));

        DB::table('adq.proceso_contratacion')->where('id', $id)->update(['nombre' => $nombre]);
        return response()->json(DB::table('adq.proceso_contratacion')->find($id));
    }

    public function toggle(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $row = DB::table('adq.proceso_contratacion')->findOrFail($id);
        DB::table('adq.proceso_contratacion')->where('id', $id)->update(['activo' => !$row->activo]);
        return response()->json(DB::table('adq.proceso_contratacion')->find($id));
    }
}
