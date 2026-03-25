<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CuadreController extends Controller
{
    // Disparar el cuadre manualmente desde el portal
    public function procesar(Request $request)
    {
        $request->validate([
            'fecha' => 'nullable|date',
        ]);

        $fecha = $request->fecha ?? Carbon::today()->toDateString();

        Artisan::call('procesar:cuadre', ['--fecha' => $fecha]);

        $output = Artisan::output();

        return response()->json([
            'message' => "Cuadre procesado para {$fecha}",
            'detalle' => trim($output),
        ]);
    }

    // Listar resultados del cuadre para una fecha
    public function listado(Request $request)
    {
        $fecha  = $request->get('fecha', Carbon::today()->toDateString());
        $depto  = $request->get('departamento_id');
        $buscar = $request->get('buscar');

        $query = DB::table('dbo.d2_cuadre_marcacion as c')
            ->join('dbo.ad_empleado as e', 'e.id_emp', '=', 'c.id_emp')
            ->join('dbo.ad_departamento as d', 'd.id_depto', '=', 'e.id_depto')
            ->whereDate('c.fecha', $fecha)
            ->select(
                'c.id_emp', 'c.identificacion', 'c.apellido', 'c.nombre',
                'c.area', 'c.falta',
                'c.hora_turno_entrada', 'c.hora_real_entrada', 'c.atraso_entrada',
                'c.hora_turno_sal_lunch', 'c.hora_real_sal_lunch',
                'c.hora_turno_ent_lunch', 'c.hora_real_ent_lunch', 'c.atraso_lunch',
                'c.hora_turno_sal', 'c.hora_real_sal', 'c.atraso_salida',
                'c.horas_totales', 'c.horas_decto', 'c.tiempo_lunch',
                'c.motivo_entrada', 'c.motivo_lunch', 'c.motivo_salida',
                'd.nombre_depto'
            )
            ->orderBy('c.apellido');

        if ($depto) {
            $query->where('e.id_depto', $depto);
        }

        if ($buscar) {
            $query->where(function ($q) use ($buscar) {
                $q->where('c.apellido',       'ilike', "%{$buscar}%")
                  ->orWhere('c.nombre',        'ilike', "%{$buscar}%")
                  ->orWhere('c.identificacion','ilike', "%{$buscar}%");
            });
        }

        return response()->json($query->get());
    }
}
