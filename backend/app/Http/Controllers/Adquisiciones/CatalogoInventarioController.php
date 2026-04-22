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

        $query = DB::table('adq.catalogo_inventario')
            ->leftJoin('adq.catalogo_nivel1', 'adq.catalogo_inventario.nivel1', '=', 'adq.catalogo_nivel1.nivel1')
            ->select(
                'adq.catalogo_inventario.nivel1',
                'adq.catalogo_inventario.nivel2',
                'adq.catalogo_inventario.descripcion',
                'adq.catalogo_inventario.asociacion_presupuestaria',
                'adq.catalogo_nivel1.descripcion as descripcion_nivel1'
            );

        if ($q) {
            $query->where(function ($qb) use ($q) {
                $qb->where('adq.catalogo_inventario.nivel2', 'ilike', "%$q%")
                   ->orWhere('adq.catalogo_inventario.descripcion', 'ilike', "%$q%");
            });
        }
        if ($nivel1) {
            $query->where('adq.catalogo_inventario.nivel1', $nivel1);
        }

        $perPage = min((int)($request->get('por_pagina', 50)), 1000);
        $items = $query->orderBy('adq.catalogo_inventario.nivel2')->paginate($perPage);
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
