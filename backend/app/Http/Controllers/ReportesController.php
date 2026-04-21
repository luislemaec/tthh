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
                'c.horas_decto',
                // Minutos justificados por tipo_horario
                DB::raw("COALESCE((
                    SELECT SUM(EXTRACT(EPOCH FROM (p.hora_hasta::timestamp - p.hora_desde::timestamp)) / 60)
                    FROM dbo.d2_permiso p
                    WHERE p.id_emp = c.id_emp
                      AND DATE(p.fecha_desde) = DATE(c.fecha)
                      AND p.estado_permiso = 'APROBADO'
                      AND p.tipo_horario = 'ENTRADA'
                ), 0) as min_just_entrada"),
                DB::raw("COALESCE((
                    SELECT SUM(EXTRACT(EPOCH FROM (p.hora_hasta::timestamp - p.hora_desde::timestamp)) / 60)
                    FROM dbo.d2_permiso p
                    WHERE p.id_emp = c.id_emp
                      AND DATE(p.fecha_desde) = DATE(c.fecha)
                      AND p.estado_permiso = 'APROBADO'
                      AND p.tipo_horario = 'ENTRE JORNADA'
                ), 0) as min_just_lunch"),
                DB::raw("COALESCE((
                    SELECT SUM(EXTRACT(EPOCH FROM (p.hora_hasta::timestamp - p.hora_desde::timestamp)) / 60)
                    FROM dbo.d2_permiso p
                    WHERE p.id_emp = c.id_emp
                      AND DATE(p.fecha_desde) = DATE(c.fecha)
                      AND p.estado_permiso = 'APROBADO'
                      AND p.tipo_horario = 'SALIDA'
                ), 0) as min_just_salida")
            );

        if ($request->filled('id_emp')) {
            $buscar = '%' . $request->id_emp . '%';
            $query->whereRaw(
                "(e.identificacion ILIKE ? OR e.apellido_emp ILIKE ? OR e.nombre_emp ILIKE ?)",
                [$buscar, $buscar, $buscar]
            );
        }
        if ($request->filled('id_depto')) {
            $query->where('e.id_depto', $request->id_depto);
        }

        $datos = $query->orderBy('c.fecha')->orderByRaw('e.apellido_emp')->get();

        // Filtrar según justificación por tipo
        $resultado = $datos->map(function ($r) {
            $pendEntrada = max(0, $r->atraso_entrada - (float)$r->min_just_entrada);
            $pendLunch   = max(0, $r->atraso_lunch   - (float)$r->min_just_lunch);
            $pendSalida  = max(0, $r->atraso_salida  - (float)$r->min_just_salida);
            $pendiente   = $pendEntrada + $pendLunch + $pendSalida;

            if ($pendiente <= 0) return null; // Totalmente justificado → no aparece

            $tieneAlgunJustificado = $r->min_just_entrada > 0
                || $r->min_just_lunch > 0
                || $r->min_just_salida > 0;

            $r->justificacion      = $tieneAlgunJustificado ? 'PARCIAL' : 'NINGUNA';
            $r->minutos_pendientes = $pendiente;
            return $r;
        })->filter()->values();

        return response()->json($resultado);
    }

    // Reporte 2: Marcaciones faltantes — base todos los empleados activos
    public function marcacionesFaltantes(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
        ]);

        // Generar lista de fechas en el rango
        $fechas = [];
        $cursor = new \DateTime($request->fecha_desde);
        $fin    = new \DateTime($request->fecha_hasta);
        while ($cursor <= $fin) {
            $fechas[] = $cursor->format('Y-m-d');
            $cursor->modify('+1 day');
        }

        // Empleados activos con su departamento
        $empQuery = DB::table('dbo.ad_empleado as e')
            ->join('dbo.ad_departamento as d', 'd.id_depto', '=', 'e.id_depto')
            ->where('e.estado', 'ACTIVO')
            ->where('e.id_depto', '!=', 999)
            ->select('e.id_emp', 'e.identificacion', 'e.apellido_emp', 'e.nombre_emp',
                     'd.id_depto', 'd.nombre_depto');

        if ($request->filled('id_emp')) {
            $buscar = '%' . $request->id_emp . '%';
            $empQuery->whereRaw(
                "(e.identificacion ILIKE ? OR e.apellido_emp ILIKE ? OR e.nombre_emp ILIKE ?)",
                [$buscar, $buscar, $buscar]
            );
        }
        if ($request->filled('id_depto')) {
            $empQuery->where('e.id_depto', $request->id_depto);
        }

        $empleados = $empQuery->orderBy('e.apellido_emp')->get();

        // Marcaciones en el rango agrupadas por empleado y fecha
        $ids = $empleados->pluck('id_emp')->toArray();
        $marcaciones = DB::table('dbo.sg_control_persona')
            ->whereIn('nro_documento', $ids)
            ->whereBetween(DB::raw('DATE(fecha_hora)'), [$request->fecha_desde, $request->fecha_hasta])
            ->selectRaw("
                nro_documento,
                DATE(fecha_hora) as fecha,
                SUM(CASE WHEN concepto = 'ENTRADA'           THEN 1 ELSE 0 END) as tiene_entrada,
                SUM(CASE WHEN concepto = 'SALIDA AL LUNCH'   THEN 1 ELSE 0 END) as tiene_sal_lunch,
                SUM(CASE WHEN concepto = 'ENTRADA DEL LUNCH' THEN 1 ELSE 0 END) as tiene_ent_lunch,
                SUM(CASE WHEN concepto = 'SALIDA'            THEN 1 ELSE 0 END) as tiene_salida
            ")
            ->groupByRaw("nro_documento, DATE(fecha_hora)")
            ->get()
            ->groupBy('nro_documento')
            ->map(fn($rows) => $rows->keyBy('fecha'));

        // Cruzar empleados × fechas
        $resultado = [];
        foreach ($empleados as $emp) {
            foreach ($fechas as $fecha) {
                $marc = $marcaciones[$emp->id_emp][$fecha] ?? null;
                $entrada  = $marc ? (int)$marc->tiene_entrada  : 0;
                $salLunch = $marc ? (int)$marc->tiene_sal_lunch : 0;
                $entLunch = $marc ? (int)$marc->tiene_ent_lunch : 0;
                $salida   = $marc ? (int)$marc->tiene_salida    : 0;

                // Solo incluir si falta al menos una marcación
                if ($entrada >= 1 && $salLunch >= 1 && $entLunch >= 1 && $salida >= 1) continue;

                $resultado[] = [
                    'fecha'          => $fecha,
                    'id_emp'         => $emp->id_emp,
                    'nombre_completo'=> trim($emp->apellido_emp) . ' ' . trim($emp->nombre_emp),
                    'nombre_depto'   => $emp->nombre_depto,
                    'tiene_entrada'  => $entrada,
                    'tiene_sal_lunch'=> $salLunch,
                    'tiene_ent_lunch'=> $entLunch,
                    'tiene_salida'   => $salida,
                ];
            }
        }

        return response()->json($resultado);
    }
}
