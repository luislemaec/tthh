<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Departamento;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{
    public function index()
    {
        $deps = Departamento::orderBy('nombre_depto')->get();
        return response()->json($deps);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre_depto' => 'required|string|max:120',
        ]);

        // Generar id_depto correlativo
        $ultimo = Departamento::max('id_depto');
        $id     = ($ultimo ?? 0) + 1;

        $dep = Departamento::create([
            'id_depto'      => $id,
            'nombre_depto'  => strtoupper($request->nombre_depto),
            'centro_de_costo' => strtoupper($request->centro_de_costo ?? ''),
        ]);

        return response()->json($dep, 201);
    }

    public function update(Request $request, $id)
    {
        $dep = Departamento::findOrFail($id);
        $request->validate([
            'nombre_depto' => 'required|string|max:120',
        ]);

        $dep->update([
            'nombre_depto'    => strtoupper($request->nombre_depto),
            'centro_de_costo' => strtoupper($request->centro_de_costo ?? $dep->centro_de_costo),
        ]);

        return response()->json($dep);
    }

    public function destroy($id)
    {
        $dep = Departamento::findOrFail($id);
        // Verificar que no tenga empleados activos
        if ($dep->empleados()->where('estado', 'ACTIVO')->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar, tiene empleados activos.'
            ], 422);
        }
        $dep->delete();
        return response()->json(['message' => 'Departamento eliminado.']);
    }
}
