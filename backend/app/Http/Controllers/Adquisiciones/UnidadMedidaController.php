<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnidadMedidaController extends Controller
{
    public function index()
    {
        $unidades = DB::table('adq.unidad_medida')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'abreviatura', 'activo']);
        return response()->json($unidades);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'      => 'required|string|max:60',
            'abreviatura' => 'required|string|max:15',
        ]);

        $id = DB::table('adq.unidad_medida')->insertGetId([
            'nombre'      => strtoupper(trim($request->nombre)),
            'abreviatura' => trim($request->abreviatura),
            'activo'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(
            DB::table('adq.unidad_medida')->find($id),
            201
        );
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre'      => 'required|string|max:60',
            'abreviatura' => 'required|string|max:15',
        ]);

        DB::table('adq.unidad_medida')->where('id', $id)->update([
            'nombre'      => strtoupper(trim($request->nombre)),
            'abreviatura' => trim($request->abreviatura),
            'updated_at'  => now(),
        ]);

        return response()->json(DB::table('adq.unidad_medida')->find($id));
    }

    public function toggle($id)
    {
        $unidad = DB::table('adq.unidad_medida')->find($id);
        if (!$unidad) return response()->json(['message' => 'No encontrado.'], 404);

        DB::table('adq.unidad_medida')->where('id', $id)->update([
            'activo'     => !$unidad->activo,
            'updated_at' => now(),
        ]);

        return response()->json(DB::table('adq.unidad_medida')->find($id));
    }
}
