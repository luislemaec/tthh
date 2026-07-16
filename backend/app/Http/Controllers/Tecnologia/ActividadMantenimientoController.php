<?php
namespace App\Http\Controllers\Tecnologia;

use App\Http\Controllers\Controller;
use App\Models\Tecnologia\ActividadMantenimiento;
use Illuminate\Http\Request;

class ActividadMantenimientoController extends Controller
{
    public function index()
    {
        return response()->json(ActividadMantenimiento::orderBy('orden')->get());
    }

    public function activas()
    {
        return response()->json(ActividadMantenimiento::where('estado', true)->orderBy('orden')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:150',
            'orden'  => 'required|integer|min:1',
        ]);

        $actividad = ActividadMantenimiento::create([
            'nombre' => $request->nombre,
            'orden'  => $request->orden,
            'estado' => true,
        ]);

        return response()->json($actividad, 201);
    }

    public function update(Request $request, $id)
    {
        $actividad = ActividadMantenimiento::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:150',
            'orden'  => 'required|integer|min:1',
            'estado' => 'nullable|boolean',
        ]);

        $actividad->update([
            'nombre' => $request->nombre,
            'orden'  => $request->orden,
            'estado' => $request->has('estado') ? $request->boolean('estado') : $actividad->estado,
        ]);

        return response()->json($actividad);
    }
}
