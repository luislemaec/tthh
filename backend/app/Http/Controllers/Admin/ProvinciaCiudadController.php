<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProvinciaCiudadController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $rows = DB::table('dbo.com_provincia as p')
            ->join('dbo.com_ciudad as c', 'c.provincia_id', '=', 'p.id')
            ->orderBy('p.nombre')->orderBy('c.nombre')
            ->select('p.id as prov_id', 'p.nombre as prov_nombre', 'c.id as ciu_id', 'c.nombre as ciu_nombre')
            ->get();

        $agrupado = $rows->groupBy('prov_id')->map(fn($cities) => [
            'id'       => $cities->first()->prov_id,
            'nombre'   => $cities->first()->prov_nombre,
            'ciudades' => $cities->map(fn($c) => ['id' => $c->ciu_id, 'nombre' => $c->ciu_nombre])->values(),
        ])->values();

        return response()->json($agrupado);
    }

    public function storeProvincia(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate(['nombre' => 'required|string|max:100']);
        $id = DB::table('dbo.com_provincia')->insertGetId(['nombre' => strtoupper($request->nombre)]);
        return response()->json(['id' => $id, 'nombre' => strtoupper($request->nombre)], 201);
    }

    public function updateProvincia(Request $request, int $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate(['nombre' => 'required|string|max:100']);
        DB::table('dbo.com_provincia')->where('id', $id)->update(['nombre' => strtoupper($request->nombre)]);
        return response()->json(['message' => 'Actualizado']);
    }

    public function destroyProvincia(Request $request, int $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        DB::table('dbo.com_ciudad')->where('provincia_id', $id)->delete();
        DB::table('dbo.com_provincia')->where('id', $id)->delete();
        return response()->json(['message' => 'Eliminado']);
    }

    public function storeCiudad(Request $request, int $provinciaId)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate(['nombre' => 'required|string|max:100']);
        $id = DB::table('dbo.com_ciudad')->insertGetId([
            'provincia_id' => $provinciaId,
            'nombre'       => strtoupper($request->nombre),
        ]);
        return response()->json(['id' => $id, 'nombre' => strtoupper($request->nombre)], 201);
    }

    public function updateCiudad(Request $request, int $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate(['nombre' => 'required|string|max:100']);
        DB::table('dbo.com_ciudad')->where('id', $id)->update(['nombre' => strtoupper($request->nombre)]);
        return response()->json(['message' => 'Actualizado']);
    }

    public function destroyCiudad(Request $request, int $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        DB::table('dbo.com_ciudad')->where('id', $id)->delete();
        return response()->json(['message' => 'Eliminado']);
    }
}
