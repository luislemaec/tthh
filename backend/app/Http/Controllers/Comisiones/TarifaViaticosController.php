<?php

namespace App\Http\Controllers\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\ComTarifaViatico;
use Illuminate\Http\Request;

class TarifaViaticosController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR'];

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        return response()->json(ComTarifaViatico::orderBy('tipo')->orderBy('descripcion')->get());
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            'descripcion' => 'required|string|max:200',
            'valor_dia'   => 'required|numeric|min:0',
            'tipo'        => 'required|in:INTERIOR,EXTERIOR,AMBOS',
        ]);

        $tarifa = ComTarifaViatico::create([
            'descripcion' => strtoupper($request->descripcion),
            'valor_dia'   => $request->valor_dia,
            'tipo'        => $request->tipo,
            'activo'      => true,
        ]);

        return response()->json($tarifa, 201);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            'descripcion' => 'required|string|max:200',
            'valor_dia'   => 'required|numeric|min:0',
            'tipo'        => 'required|in:INTERIOR,EXTERIOR,AMBOS',
            'activo'      => 'boolean',
        ]);

        $tarifa = ComTarifaViatico::findOrFail($id);
        $tarifa->update([
            'descripcion' => strtoupper($request->descripcion),
            'valor_dia'   => $request->valor_dia,
            'tipo'        => $request->tipo,
            'activo'      => $request->boolean('activo', $tarifa->activo),
        ]);

        return response()->json($tarifa);
    }

    public function destroy(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        ComTarifaViatico::findOrFail($id)->delete();
        return response()->json(['message' => 'Eliminado']);
    }
}
