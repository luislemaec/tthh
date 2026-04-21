<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogoInventarioController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q', '');
        $nivel1 = $request->get('nivel1', '');

        $query = DB::table('adq.catalogo_inventario');

        if ($q) {
            $query->where(function ($qb) use ($q) {
                $qb->where('nivel2', 'ilike', "%$q%")
                   ->orWhere('descripcion', 'ilike', "%$q%");
            });
        }
        if ($nivel1) {
            $query->where('nivel1', $nivel1);
        }

        $items = $query
            ->join('adq.catalogo_nivel1 as n1', 'adq.catalogo_inventario.nivel1', '=', 'n1.nivel1')
            ->select('adq.catalogo_inventario.*', 'n1.descripcion as descripcion_nivel1')
            ->orderBy('adq.catalogo_inventario.nivel2')
            ->paginate(50);
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nivel1'  => 'required|string|size:2',
            'nivel2'  => 'required|string|size:6|unique:pgsql.adq.catalogo_inventario,nivel2',
            'descripcion' => 'required|string|max:300',
            'asociacion_presupuestaria' => 'nullable|string|max:150',
        ]);

        DB::table('adq.catalogo_inventario')->insert([
            'nivel1'                    => strtoupper($request->nivel1),
            'nivel2'                    => $request->nivel2,
            'descripcion'               => strtoupper($request->descripcion),
            'asociacion_presupuestaria' => $request->asociacion_presupuestaria,
        ]);

        return response()->json(['message' => 'Ítem creado.'], 201);
    }

    public function update(Request $request, $nivel2)
    {
        $request->validate([
            'nivel1'  => 'required|string|size:2',
            'descripcion' => 'required|string|max:300',
            'asociacion_presupuestaria' => 'nullable|string|max:150',
        ]);

        DB::table('adq.catalogo_inventario')->where('nivel2', $nivel2)->update([
            'nivel1'                    => strtoupper($request->nivel1),
            'descripcion'               => strtoupper($request->descripcion),
            'asociacion_presupuestaria' => $request->asociacion_presupuestaria,
        ]);

        return response()->json(['message' => 'Ítem actualizado.']);
    }

    public function destroy($nivel2)
    {
        $enUso = DB::table('adq.articulo')->where('nivel2', $nivel2)->exists();
        if ($enUso) {
            return response()->json(['message' => 'No se puede eliminar: hay artículos vinculados a este ítem.'], 422);
        }
        DB::table('adq.catalogo_inventario')->where('nivel2', $nivel2)->delete();
        return response()->json(['message' => 'Ítem eliminado.']);
    }

    public function nivel1s()
    {
        $items = DB::table('adq.catalogo_nivel1')
            ->orderBy('nivel1')
            ->get(['nivel1', 'descripcion']);
        return response()->json($items);
    }
}
