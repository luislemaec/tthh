<?php
namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\PlanPreventivoCab;
use App\Models\Transporte\PlanPreventivoDet;
use Illuminate\Http\Request;

class PlanPreventivoController extends Controller
{
    public function index(Request $request)
    {
        $query = PlanPreventivoCab::with(['vehiculo', 'actividades'])->orderBy('km_hito');

        if ($request->filled('vehiculo_id')) {
            $query->where('vehiculo_id', $request->vehiculo_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'vehiculo_id'  => 'required|exists:pgsql.dbo.trans_vehiculo,id',
            'km_hito'      => 'required|integer|min:1',
            'nombre'       => 'required|string|max:100',
            'actividades'  => 'required|array|min:1',
            'actividades.*.tipo_actividad' => 'required|in:MO,RE,CL',
            'actividades.*.actividad'      => 'required|string',
            'actividades.*.cantidad'       => 'required|integer|min:1',
        ]);

        $cab = PlanPreventivoCab::create([
            'vehiculo_id' => $request->vehiculo_id,
            'km_hito'     => $request->km_hito,
            'nombre'      => $request->nombre,
            'estado'      => 'ACTIVO',
        ]);

        foreach ($request->actividades as $i => $act) {
            PlanPreventivoDet::create([
                'cab_id'         => $cab->id,
                'orden'          => $i + 1,
                'tipo_actividad' => $act['tipo_actividad'],
                'cantidad'       => $act['cantidad'] ?? 1,
                'actividad'      => $act['actividad'],
            ]);
        }

        return response()->json($cab->load('actividades'), 201);
    }

    public function update(Request $request, $id)
    {
        $cab = PlanPreventivoCab::findOrFail($id);

        $request->validate([
            'km_hito'      => 'required|integer|min:1',
            'nombre'       => 'required|string|max:100',
            'actividades'  => 'required|array|min:1',
            'actividades.*.tipo_actividad' => 'required|in:MO,RE,CL',
            'actividades.*.actividad'      => 'required|string',
            'actividades.*.cantidad'       => 'required|integer|min:1',
            'estado'       => 'nullable|in:ACTIVO,INACTIVO',
        ]);

        $cab->update([
            'km_hito' => $request->km_hito,
            'nombre'  => $request->nombre,
            'estado'  => $request->estado ?? $cab->estado,
        ]);

        PlanPreventivoDet::where('cab_id', $cab->id)->delete();

        foreach ($request->actividades as $i => $act) {
            PlanPreventivoDet::create([
                'cab_id'         => $cab->id,
                'orden'          => $i + 1,
                'tipo_actividad' => $act['tipo_actividad'],
                'cantidad'       => $act['cantidad'] ?? 1,
                'actividad'      => $act['actividad'],
            ]);
        }

        return response()->json($cab->load('actividades'));
    }
}
