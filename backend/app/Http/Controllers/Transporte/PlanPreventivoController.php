<?php
namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\PlanPreventivoCab;
use App\Models\Transporte\PlanPreventivoDet;
use App\Models\Transporte\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanPreventivoController extends Controller
{
    public function index(Request $request)
    {
        $query = PlanPreventivoCab::with(['vehiculo', 'actividades'])->orderBy('km_hito');

        if ($request->filled('vehiculo_id')) {
            $query->where('vehiculo_id', $request->vehiculo_id);
        }

        $planes = $query->get();

        // km_hito es un hito único: un plan que ya tuvo un mantenimiento FINALIZADO no se
        // puede volver a ejecutar (ver TransporteController::storeMtto). Se expone aquí para
        // que el frontend lo muestre como "Ya ejecutado" antes de intentar crear otro.
        $ejecutados = DB::table('dbo.trans_mantenimiento')
            ->select('plan_preventivo_id', DB::raw('MAX(fecha_finalizacion) as fecha_ejecutado'))
            ->whereNotNull('plan_preventivo_id')
            ->where('estado', 'FINALIZADO')
            ->groupBy('plan_preventivo_id')
            ->get()
            ->keyBy('plan_preventivo_id');

        $planes->each(function ($p) use ($ejecutados) {
            $reg = $ejecutados->get($p->id);
            $p->ejecutado = (bool) $reg;
            $p->fecha_ejecutado = $reg->fecha_ejecutado ?? null;
        });

        return response()->json($planes);
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

    public function importarCsv(Request $request)
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($request->file('archivo')->getRealPath(), 'r');
        $encabezado = fgetcsv($handle); // skip header

        $errores  = [];
        $grupos   = [];
        $fila     = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $fila++;
            if (count($row) < 6) {
                $errores[] = "Fila $fila: faltan columnas.";
                continue;
            }

            [$placa, $km_hito, $nombre, $tipo_actividad, $actividad, $cantidad] = array_map('trim', $row);

            if (!in_array(strtoupper($tipo_actividad), ['MO', 'RE', 'CL'])) {
                $errores[] = "Fila $fila: tipo_actividad '$tipo_actividad' inválido (MO/RE/CL).";
                continue;
            }

            $vehiculo = Vehiculo::where('placa', strtoupper($placa))->first();
            if (!$vehiculo) {
                $errores[] = "Fila $fila: placa '$placa' no encontrada.";
                continue;
            }

            $clave = $vehiculo->id . '|' . (int)$km_hito . '|' . trim($nombre);
            if (!isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'vehiculo_id' => $vehiculo->id,
                    'km_hito'     => (int)$km_hito,
                    'nombre'      => trim($nombre),
                    'actividades' => [],
                ];
            }
            $grupos[$clave]['actividades'][] = [
                'tipo_actividad' => strtoupper($tipo_actividad),
                'actividad'      => strtoupper(trim($actividad)),
                'cantidad'       => max(1, (int)$cantidad),
            ];
        }
        fclose($handle);

        if (!empty($errores)) {
            return response()->json(['message' => 'Errores en el CSV.', 'errores' => $errores], 422);
        }

        DB::transaction(function () use ($grupos) {
            foreach ($grupos as $data) {
                $cab = PlanPreventivoCab::create([
                    'vehiculo_id' => $data['vehiculo_id'],
                    'km_hito'     => $data['km_hito'],
                    'nombre'      => $data['nombre'],
                    'estado'      => 'ACTIVO',
                ]);
                foreach ($data['actividades'] as $i => $act) {
                    PlanPreventivoDet::create([
                        'cab_id'         => $cab->id,
                        'orden'          => $i + 1,
                        'tipo_actividad' => $act['tipo_actividad'],
                        'cantidad'       => $act['cantidad'],
                        'actividad'      => $act['actividad'],
                    ]);
                }
            }
        });

        $total = count($grupos);
        return response()->json(['message' => "Se importaron $total plan(es) correctamente."]);
    }
}
