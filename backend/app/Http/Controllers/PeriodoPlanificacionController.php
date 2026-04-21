<?php
namespace App\Http\Controllers;

use App\Models\PeriodoPlanificacion;
use Illuminate\Http\Request;

class PeriodoPlanificacionController extends Controller
{
    // Listar todos los períodos
    public function index()
    {
        return response()->json(
            PeriodoPlanificacion::orderByDesc('anio')->orderBy('fecha_inicio')->get()
        );
    }

    // Período activo para el año en curso (para el botón del empleado)
    public function activo()
    {
        $hoy = now()->toDateString();
        $periodo = PeriodoPlanificacion::where('estado', 'ACTIVO')
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin',    '>=', $hoy)
            ->first();
        return response()->json($periodo);
    }

    // Crear período
    public function store(Request $request)
    {
        $request->validate([
            'anio'        => 'required|integer|min:2000|max:2100',
            'fecha_inicio'=> 'required|date',
            'fecha_fin'   => 'required|date|after_or_equal:fecha_inicio',
        ]);

        // Solo un período ACTIVO a la vez por año
        $existeActivo = PeriodoPlanificacion::where('anio', $request->anio)
            ->where('estado', 'ACTIVO')
            ->exists();

        if ($existeActivo) {
            return response()->json([
                'message' => "Ya existe un período activo para el año {$request->anio}"
            ], 422);
        }

        $periodo = PeriodoPlanificacion::create([
            'anio'        => $request->anio,
            'fecha_inicio'=> $request->fecha_inicio,
            'fecha_fin'   => $request->fecha_fin,
            'estado'      => 'ACTIVO',
        ]);

        return response()->json($periodo, 201);
    }

    // Actualizar período
    public function update(Request $request, $id)
    {
        $request->validate([
            'anio'        => 'required|integer|min:2000|max:2100',
            'fecha_inicio'=> 'required|date',
            'fecha_fin'   => 'required|date|after_or_equal:fecha_inicio',
            'estado'      => 'required|in:ACTIVO,INACTIVO',
        ]);

        $periodo = PeriodoPlanificacion::findOrFail($id);

        // Si se activa, desactivar otros del mismo año
        if ($request->estado === 'ACTIVO') {
            PeriodoPlanificacion::where('anio', $request->anio)
                ->where('id', '!=', $id)
                ->update(['estado' => 'INACTIVO']);
        }

        $periodo->update($request->only('anio', 'fecha_inicio', 'fecha_fin', 'estado'));

        return response()->json($periodo);
    }

    // Eliminar período
    public function destroy($id)
    {
        PeriodoPlanificacion::findOrFail($id)->delete();
        return response()->json(['message' => 'Período eliminado']);
    }
}
