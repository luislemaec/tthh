<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AportesIess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AportesIessController extends Controller
{
    // GET /api/admin/aportes-iess
    public function index()
    {
        $aportes = AportesIess::orderByDesc('fecha_desde')->get();
        return response()->json($aportes);
    }

    // GET /api/admin/aportes-iess/vigentes
    // Retorna las tasas vigentes por modalidad (para usar en otros módulos)
    public function vigentes()
    {
        $aportes = AportesIess::whereNull('fecha_hasta')
            ->orWhere('fecha_hasta', '>=', now()->toDateString())
            ->orderByDesc('fecha_desde')
            ->get()
            ->unique('modalidad');

        return response()->json($aportes->values());
    }

    // POST /api/admin/aportes-iess
    // Registra nuevas tasas y cierra las anteriores de la misma modalidad
    public function store(Request $request)
    {
        $request->validate([
            'modalidad'         => 'required|string|max:100',
            'aporte_individual' => 'required|numeric|min:0|max:100',
            'aporte_patronal'   => 'required|numeric|min:0|max:100',
            'fecha_desde'       => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            // Cerrar la tasa vigente anterior de esta modalidad
            AportesIess::where('modalidad', $request->modalidad)
                ->whereNull('fecha_hasta')
                ->update(['fecha_hasta' => date('Y-m-d', strtotime($request->fecha_desde . ' -1 day'))]);

            // Crear nueva tasa
            $aporte = AportesIess::create([
                'modalidad'         => $request->modalidad,
                'aporte_individual' => $request->aporte_individual,
                'aporte_patronal'   => $request->aporte_patronal,
                'fecha_desde'       => $request->fecha_desde,
                'fecha_hasta'       => null,
                'created_at'        => now(),
            ]);

            DB::commit();
            return response()->json($aporte, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar los aportes: ' . $e->getMessage()], 500);
        }
    }

    // DELETE /api/admin/aportes-iess/{id}
    public function destroy($id)
    {
        AportesIess::findOrFail($id)->delete();
        return response()->json(['message' => 'Eliminado correctamente.']);
    }

    // PUT /api/admin/aportes-iess/{id}
    public function update(Request $request, $id)
    {
        $request->validate([
            'modalidad'         => 'required|string|max:100',
            'aporte_individual' => 'required|numeric|min:0|max:100',
            'aporte_patronal'   => 'required|numeric|min:0|max:100',
            'fecha_desde'       => 'required|date',
        ]);

        $aporte = AportesIess::findOrFail($id);
        $aporte->update([
            'modalidad'         => $request->modalidad,
            'aporte_individual' => $request->aporte_individual,
            'aporte_patronal'   => $request->aporte_patronal,
            'fecha_desde'       => $request->fecha_desde,
            'fecha_hasta'       => $request->fecha_hasta ?? $aporte->fecha_hasta,
        ]);

        return response()->json($aporte);
    }
}
