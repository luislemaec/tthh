<?php
namespace App\Http\Controllers\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\ComFuncionarioExterno;
use Illuminate\Http\Request;

class FuncionarioExternoController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        return response()->json(ComFuncionarioExterno::orderBy('nombres')->get());
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'cedula'  => 'required|string|max:20|unique:pgsql.dbo.com_funcionario_externo,cedula',
            'nombres' => 'required|string|max:200',
            'cargo'   => 'required|string|max:200',
        ]);

        $f = ComFuncionarioExterno::create([
            'cedula'  => $request->cedula,
            'nombres' => strtoupper($request->nombres),
            'cargo'   => strtoupper($request->cargo),
            'activo'  => true,
        ]);

        return response()->json($f, 201);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $f = ComFuncionarioExterno::findOrFail($id);

        $request->validate([
            'nombres' => 'sometimes|string|max:200',
            'cargo'   => 'sometimes|string|max:200',
            'activo'  => 'sometimes|boolean',
        ]);

        $f->update([
            'nombres' => isset($request->nombres) ? strtoupper($request->nombres) : $f->nombres,
            'cargo'   => isset($request->cargo)   ? strtoupper($request->cargo)   : $f->cargo,
            'activo'  => $request->has('activo') ? $request->activo : $f->activo,
        ]);

        return response()->json($f);
    }

    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $f = ComFuncionarioExterno::findOrFail($id);
        $f->update(['activo' => false]);
        return response()->json(['message' => 'Funcionario desactivado']);
    }
}
