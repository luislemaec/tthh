<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{
    // Reporte 1: Atrasos desde d2_cuadre_marcacion
    public function atrasos(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
        ]);

        $query = DB::table('dbo.d2_cuadre_marcacion as c')
            ->join('dbo.ad_empleado as e', 'e.id_emp', '=', 'c.id_emp')
            ->join('dbo.ad_departamento as d', 'd.id_depto', '=', 'e.id_depto')
            ->whereBetween(DB::raw('DATE(c.fecha)'), [$request->fecha_desde, $request->fecha_hasta])
            ->where(function ($q) {
                $q->where('c.atraso_entrada', '>', 0)
                  ->orWhere('c.atraso_lunch',  '>', 0)
                  ->orWhere('c.atraso_salida', '>', 0);
            })
            ->select(
                'c.fecha',
                'e.id_emp',
                DB::raw("e.apellido_emp || ' ' || e.nombre_emp as nombre_completo"),
                'd.nombre_depto',
                'c.hora_turno_entrada',
                'c.hora_real_entrada',
                'c.atraso_entrada',
                'c.atraso_lunch',
                'c.atraso_salida',
                'c.horas_decto'
            );

        if ($request->filled('id_emp')) {
            $query->where('c.id_emp', $request->id_emp);
        }
        if ($request->filled('id_depto')) {
            $query->where('e.id_depto', $request->id_depto);
        }

        return response()->json(
            $query->orderBy('c.fecha')->orderByRaw('e.apellido_emp')->get()
        );
    }

    // Reporte 2: Marcaciones faltantes (al menos una de las 4 sin registrar)
    public function marcacionesFaltantes(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
        ]);

        $query = DB::table('dbo.sg_control_persona as s')
            ->join('dbo.ad_empleado as e', 'e.id_emp', '=', 's.nro_documento')
            ->join('dbo.ad_departamento as d', 'd.id_depto', '=', 'e.id_depto')
            ->whereBetween(DB::raw('DATE(s.fecha_hora)'), [$request->fecha_desde, $request->fecha_hasta])
            ->where('e.estado', 'ACTIVO')
            ->selectRaw("
                DATE(s.fecha_hora) as fecha,
                e.id_emp,
                e.apellido_emp || ' ' || e.nombre_emp as nombre_completo,
                d.nombre_depto,
                SUM(CASE WHEN s.concepto = 'ENTRADA'           THEN 1 ELSE 0 END) as tiene_entrada,
                SUM(CASE WHEN s.concepto = 'SALIDA AL LUNCH'   THEN 1 ELSE 0 END) as tiene_sal_lunch,
                SUM(CASE WHEN s.concepto = 'ENTRADA DEL LUNCH' THEN 1 ELSE 0 END) as tiene_ent_lunch,
                SUM(CASE WHEN s.concepto = 'SALIDA'            THEN 1 ELSE 0 END) as tiene_salida
            ")
            ->groupByRaw("DATE(s.fecha_hora), e.id_emp, e.apellido_emp, e.nombre_emp, d.nombre_depto")
            ->havingRaw("
                SUM(CASE WHEN s.concepto = 'ENTRADA'           THEN 1 ELSE 0 END) = 0
                OR SUM(CASE WHEN s.concepto = 'SALIDA AL LUNCH'   THEN 1 ELSE 0 END) = 0
                OR SUM(CASE WHEN s.concepto = 'ENTRADA DEL LUNCH' THEN 1 ELSE 0 END) = 0
                OR SUM(CASE WHEN s.concepto = 'SALIDA'            THEN 1 ELSE 0 END) = 0
            ");

        if ($request->filled('id_emp')) {
            $query->where('e.id_emp', $request->id_emp);
        }
        if ($request->filled('id_depto')) {
            $query->where('e.id_depto', $request->id_depto);
        }

        return response()->json(
            $query->orderBy('fecha')->orderByRaw('e.apellido_emp')->get()
        );
    }
}
