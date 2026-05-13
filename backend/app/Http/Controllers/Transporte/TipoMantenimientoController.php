<?php
namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\TipoMantenimiento;
use Illuminate\Http\Request;

class TipoMantenimientoController extends Controller
{
    public function index()
    {
        return response()->json(TipoMantenimiento::orderBy('nombre')->get());
    }

    public function activos()
    {
        return response()->json(TipoMantenimiento::where('estado', 'ACTIVO')->orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'    => 'required|string|max:100',
            'categoria' => 'required|in:PREVENTIVO,CORRECTIVO,AMBOS',
        ]);

        $tipo = TipoMantenimiento::create([
            'nombre'    => strtoupper($request->nombre),
            'categoria' => $request->categoria,
            'estado'    => 'ACTIVO',
        ]);

        return response()->json($tipo, 201);
    }

    public function update(Request $request, $id)
    {
        $tipo = TipoMantenimiento::findOrFail($id);

        $request->validate([
            'nombre'    => 'required|string|max:100',
            'categoria' => 'required|in:PREVENTIVO,CORRECTIVO,AMBOS',
            'estado'    => 'nullable|in:ACTIVO,INACTIVO',
        ]);

        $tipo->update([
            'nombre'    => strtoupper($request->nombre),
            'categoria' => $request->categoria,
            'estado'    => $request->estado ?? $tipo->estado,
        ]);

        return response()->json($tipo);
    }
}
