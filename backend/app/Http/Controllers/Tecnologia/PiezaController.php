<?php
namespace App\Http\Controllers\Tecnologia;

use App\Http\Controllers\Controller;
use App\Models\Tecnologia\Pieza;
use App\Models\Tecnologia\PiezaMovimiento;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;

class PiezaController extends Controller
{
    public function index(Request $request)
    {
        $query = Pieza::with('equipo')->orderBy('descripcion');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('busqueda')) {
            $b = $request->busqueda;
            $query->where(function ($q) use ($b) {
                $q->where('codigo', 'ILIKE', "%$b%")
                  ->orWhere('serie', 'ILIKE', "%$b%")
                  ->orWhere('descripcion', 'ILIKE', "%$b%");
            });
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo'        => 'nullable|string|max:50',
            'serie'         => 'nullable|string|max:100',
            'descripcion'   => 'required|string|max:300',
            'fecha_entrega' => 'required|date',
            'observaciones' => 'nullable|string',
        ]);

        $pieza = Pieza::create(array_merge(
            $request->only(['codigo', 'serie', 'descripcion', 'fecha_entrega', 'observaciones']),
            ['estado' => 'DISPONIBLE', 'created_by' => $request->user()->id_emp]
        ));

        return response()->json($pieza, 201);
    }

    public function update(Request $request, $id)
    {
        $pieza = Pieza::findOrFail($id);

        $request->validate([
            'codigo'        => 'nullable|string|max:50',
            'serie'         => 'nullable|string|max:100',
            'descripcion'   => 'required|string|max:300',
            'fecha_entrega' => 'required|date',
            'observaciones' => 'nullable|string',
        ]);

        $pieza->update(array_merge(
            $request->only(['codigo', 'serie', 'descripcion', 'fecha_entrega', 'observaciones']),
            ['updated_by' => $request->user()->id_emp]
        ));

        return response()->json($pieza->load('equipo'));
    }

    public function instalar(Request $request, $id)
    {
        $pieza = Pieza::findOrFail($id);

        if ($pieza->estado !== 'DISPONIBLE') {
            return response()->json(['message' => 'La pieza no está disponible para instalar.'], 422);
        }

        $request->validate([
            'equipo_id'          => 'required|exists:pgsql.dbo.ti_equipo,id',
            'fecha_instalacion'  => 'required|date',
            'mantenimiento_id'   => 'nullable|exists:pgsql.dbo.ti_mantenimiento,id',
            'observacion'        => 'nullable|string',
        ]);

        PiezaMovimiento::create([
            'pieza_id'          => $pieza->id,
            'equipo_id'         => $request->equipo_id,
            'mantenimiento_id'  => $request->mantenimiento_id,
            'fecha_instalacion' => $request->fecha_instalacion,
            'observacion'       => $request->observacion,
            'usuario_instala'   => $request->user()->id_emp,
        ]);

        $pieza->update(['estado' => 'INSTALADA', 'equipo_id' => $request->equipo_id, 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.ti_pieza', $pieza->id, 'INSTALAR',
            ['estado' => 'DISPONIBLE'],
            ['estado' => 'INSTALADA', 'equipo_id' => $request->equipo_id],
            $request, "Instalación de pieza {$pieza->descripcion} en equipo #{$request->equipo_id}");

        return response()->json($pieza->fresh('equipo'));
    }

    public function retirar(Request $request, $id)
    {
        $pieza = Pieza::findOrFail($id);

        if ($pieza->estado !== 'INSTALADA') {
            return response()->json(['message' => 'La pieza no está instalada.'], 422);
        }

        $request->validate([
            'motivo_retiro' => 'required|in:REEMPLAZO,DAÑO,DESINSTALACION,OTRO',
            'fecha_retiro'  => 'nullable|date',
            'observacion'   => 'nullable|string',
        ]);

        $movimiento = PiezaMovimiento::where('pieza_id', $pieza->id)->whereNull('fecha_retiro')->firstOrFail();

        $movimiento->update([
            'fecha_retiro'   => $request->fecha_retiro ?? now()->toDateString(),
            'motivo_retiro'  => $request->motivo_retiro,
            'observacion'    => $request->observacion,
            'usuario_retira' => $request->user()->id_emp,
        ]);

        $nuevoEstado = $request->motivo_retiro === 'DAÑO' ? 'DE_BAJA' : 'DISPONIBLE';
        $pieza->update(['estado' => $nuevoEstado, 'equipo_id' => null, 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.ti_pieza', $pieza->id, 'RETIRAR',
            ['estado' => 'INSTALADA'],
            ['estado' => $nuevoEstado, 'motivo_retiro' => $request->motivo_retiro],
            $request, "Retiro de pieza {$pieza->descripcion}");

        return response()->json($pieza->fresh('equipo'));
    }

    public function marcarBaja(Request $request, $id)
    {
        $pieza = Pieza::findOrFail($id);

        if ($pieza->estado === 'INSTALADA') {
            return response()->json(['message' => 'Debe retirar la pieza antes de darla de baja.'], 422);
        }

        $pieza->update(['estado' => 'DE_BAJA', 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.ti_pieza', $pieza->id, 'DAR_DE_BAJA', null, ['estado' => 'DE_BAJA'],
            $request, "Baja de pieza {$pieza->descripcion}");

        return response()->json($pieza);
    }

    public function marcarDisponible(Request $request, $id)
    {
        $pieza = Pieza::findOrFail($id);

        if ($pieza->estado === 'INSTALADA') {
            return response()->json(['message' => 'La pieza ya está instalada.'], 422);
        }

        $pieza->update(['estado' => 'DISPONIBLE', 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.ti_pieza', $pieza->id, 'MARCAR_DISPONIBLE', null, ['estado' => 'DISPONIBLE'],
            $request, "Pieza {$pieza->descripcion} marcada disponible");

        return response()->json($pieza);
    }

    public function historial($id)
    {
        $pieza = Pieza::findOrFail($id);

        return response()->json(
            PiezaMovimiento::with(['equipo.asignacionActiva.empleado'])
                ->where('pieza_id', $pieza->id)
                ->orderBy('fecha_instalacion', 'desc')
                ->get()
        );
    }

    public function porEquipo($equipoId)
    {
        return response()->json(
            PiezaMovimiento::with('pieza')
                ->where('equipo_id', $equipoId)
                ->orderBy('fecha_instalacion', 'desc')
                ->get()
        );
    }
}
