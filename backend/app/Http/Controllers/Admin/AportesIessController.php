<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AportesIess;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AportesIessController extends Controller
{
    private const ROLES_NOMINA = ['ADMINISTRADOR', 'TH NOMINA', 'TALENTO HUMANO'];

    // GET /api/admin/aportes-iess
    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $aportes = AportesIess::orderByDesc('fecha_desde')->get();
        return response()->json($aportes);
    }

    // GET /api/admin/aportes-iess/vigentes
    public function vigentes(Request $request)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $aportes = AportesIess::whereNull('fecha_hasta')
            ->orWhere('fecha_hasta', '>=', now()->toDateString())
            ->orderByDesc('fecha_desde')
            ->get()
            ->unique('modalidad');

        return response()->json($aportes->values());
    }

    // POST /api/admin/aportes-iess
    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $request->validate([
            'modalidad'         => 'required|string|max:100',
            'aporte_individual' => 'required|numeric|min:0|max:100',
            'aporte_patronal'   => 'required|numeric|min:0|max:100',
            'iece_patronal'     => 'nullable|numeric|min:0|max:100',
            'iece_personal'     => 'nullable|numeric|min:0|max:100',
            'secap_patronal'    => 'nullable|numeric|min:0|max:100',
            'secap_personal'    => 'nullable|numeric|min:0|max:100',
            'fecha_desde'       => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            AportesIess::where('modalidad', $request->modalidad)
                ->whereNull('fecha_hasta')
                ->update(['fecha_hasta' => date('Y-m-d', strtotime($request->fecha_desde . ' -1 day'))]);

            $aporte = AportesIess::create([
                'modalidad'         => $request->modalidad,
                'aporte_individual' => $request->aporte_individual,
                'aporte_patronal'   => $request->aporte_patronal,
                'iece_patronal'     => $request->input('iece_patronal', 0),
                'iece_personal'     => $request->input('iece_personal', 0),
                'secap_patronal'    => $request->input('secap_patronal', 0),
                'secap_personal'    => $request->input('secap_personal', 0),
                'fecha_desde'       => $request->fecha_desde,
                'fecha_hasta'       => null,
                'created_at'        => now(),
                'created_by'        => $request->user()->id_emp,
            ]);

            DB::commit();

            // Sin auditar hasta hoy — esta tasa alimenta directamente el aporte patronal/personal
            // de TODO el Rol de Pagos (RolPagoController::tasasVigentes(), spec 09 §4.4); un
            // cambio aquí sin traza hace imposible reconstruir después con qué tasa se calculó
            // un rol de pagos específico.
            AuditoriaService::log('dbo.d2_aportes_iess', $aporte->id_aporte, 'CREAR', null,
                ['modalidad' => $aporte->modalidad, 'aporte_individual' => $aporte->aporte_individual, 'aporte_patronal' => $aporte->aporte_patronal],
                $request, "Nueva tasa IESS {$aporte->modalidad} desde {$aporte->fecha_desde}");

            return response()->json($aporte, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar los aportes: ' . $e->getMessage()], 500);
        }
    }

    // PUT /api/admin/aportes-iess/{id}
    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $request->validate([
            'modalidad'         => 'required|string|max:100',
            'aporte_individual' => 'required|numeric|min:0|max:100',
            'aporte_patronal'   => 'required|numeric|min:0|max:100',
            'iece_patronal'     => 'nullable|numeric|min:0|max:100',
            'iece_personal'     => 'nullable|numeric|min:0|max:100',
            'secap_patronal'    => 'nullable|numeric|min:0|max:100',
            'secap_personal'    => 'nullable|numeric|min:0|max:100',
            'fecha_desde'       => 'required|date',
        ]);

        $aporte = AportesIess::findOrFail($id);
        $anterior = ['modalidad' => $aporte->modalidad, 'aporte_individual' => $aporte->aporte_individual, 'aporte_patronal' => $aporte->aporte_patronal, 'fecha_hasta' => $aporte->fecha_hasta];

        $aporte->update([
            'modalidad'         => $request->modalidad,
            'aporte_individual' => $request->aporte_individual,
            'aporte_patronal'   => $request->aporte_patronal,
            'iece_patronal'     => $request->input('iece_patronal', $aporte->iece_patronal),
            'iece_personal'     => $request->input('iece_personal', $aporte->iece_personal),
            'secap_patronal'    => $request->input('secap_patronal', $aporte->secap_patronal),
            'secap_personal'    => $request->input('secap_personal', $aporte->secap_personal),
            'fecha_desde'       => $request->fecha_desde,
            'fecha_hasta'       => $request->fecha_hasta ?? $aporte->fecha_hasta,
            'updated_at'        => now(),
            'updated_by'        => $request->user()->id_emp,
        ]);

        AuditoriaService::log('dbo.d2_aportes_iess', $aporte->id_aporte, 'ACTUALIZAR', $anterior,
            ['modalidad' => $aporte->modalidad, 'aporte_individual' => $aporte->aporte_individual, 'aporte_patronal' => $aporte->aporte_patronal, 'fecha_hasta' => $aporte->fecha_hasta],
            $request, "Edición tasa IESS {$aporte->modalidad}");

        return response()->json($aporte);
    }

    // DELETE /api/admin/aportes-iess/{id}
    public function destroy(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $aporte = AportesIess::findOrFail($id);
        $snapshot = ['modalidad' => $aporte->modalidad, 'aporte_individual' => $aporte->aporte_individual, 'aporte_patronal' => $aporte->aporte_patronal];
        $aporte->delete();

        AuditoriaService::log('dbo.d2_aportes_iess', $id, 'ELIMINAR', $snapshot, null,
            $request, "Eliminación tasa IESS {$snapshot['modalidad']}");

        return response()->json(['message' => 'Eliminado correctamente.']);
    }
}
