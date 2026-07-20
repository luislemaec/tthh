<?php
namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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

            if ($pendiente <= 0) return null;

            $r->justificacion      = ($r->min_just_entrada > 0 || $r->min_just_lunch > 0 || $r->min_just_salida > 0) ? 'PARCIAL' : 'NINGUNA';
            $r->minutos_pendientes = $pendiente;
            return $r;
        })->filter()->values();

        $formato = $request->get('formato');
        if ($formato === 'excel') return $this->exportarAtrasosExcel($resultado, $request);
        if ($formato === 'pdf')   return $this->exportarAtrasosPdf($resultado, $request);
        return response()->json($resultado);
    }

    private function exportarAtrasosExcel($datos, $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Atrasos');

        $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';

        // Encabezado institucional
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', strtoupper($nombreInst));
        $sheet->mergeCells('A2:J2');
        $sheet->setCellValue('A2', 'REPORTE DE ATRASOS');
        $sheet->mergeCells('A3:J3');
        $sheet->setCellValue('A3', 'Período: ' . $request->fecha_desde . ' al ' . $request->fecha_hasta);

        foreach (['A1','A2','A3'] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true);
            $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getStyle('A1')->getFont()->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(11);

        // Cabecera de columnas
        $cabeceras = ['Fecha','Empleado','Departamento','H. Programada','H. Real Entrada','Atr. Entrada','Atr. Lunch','Sal. Anticipada','H. Descuento','Justificación'];
        $sheet->fromArray($cabeceras, null, 'A5');
        $sheet->getStyle('A5:J5')->getFont()->setBold(true);
        $sheet->getStyle('A5:J5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B5447');
        $sheet->getStyle('A5:J5')->getFont()->getColor()->setARGB('FFFFFFFF');

        $fila = 6;
        foreach ($datos as $r) {
            $decHora = function ($v) {
                if (!$v && $v !== 0) return '—';
                $h = floor($v); $m = round(($v - $h) * 60);
                return sprintf('%02d:%02d', $h, $m);
            };
            $minTexto = function ($min) {
                if (!$min || $min <= 0) return '—';
                $h = intdiv($min, 60); $m = $min % 60;
                return $h > 0 ? ($m > 0 ? "{$h}h {$m}min" : "{$h}h") : "{$m}min";
            };
            $sheet->fromArray([
                substr($r->fecha, 0, 10),
                $r->nombre_completo,
                $r->nombre_depto,
                $decHora($r->hora_turno_entrada),
                $decHora($r->hora_real_entrada),
                $minTexto($r->atraso_entrada),
                $minTexto($r->atraso_lunch),
                $minTexto($r->atraso_salida),
                $minTexto(round($r->horas_decto * 60)),
                $r->justificacion === 'PARCIAL' ? "Parcial ({$minTexto($r->minutos_pendientes)} pend.)" : 'Sin justificar',
            ], null, "A{$fila}");
            if ($fila % 2 === 0) {
                $sheet->getStyle("A{$fila}:J{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF4FBF8');
            }
            $fila++;
        }

        foreach (range('A', 'J') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        ob_start(); $writer->save('php://output'); $content = ob_get_clean();
        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="reporte_atrasos_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.xlsx"',
        ]);
    }

    private function exportarAtrasosPdf($datos, $request)
    {
        $logo       = base64_encode(file_get_contents(public_path('logo.png')));
        $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
        $generadoPor = trim($request->user()->apellido_emp) . ' ' . trim($request->user()->nombre_emp);

        $pdf = Pdf::loadView('reportes.reporte_atrasos', compact('datos', 'logo', 'nombreInst', 'generadoPor', 'request'))
            ->setPaper('a4', 'landscape');
        return $pdf->download('reporte_atrasos_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.pdf');
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

        // Vacaciones aprobadas en el rango para etiquetar filas
        $vacaciones = DB::table('dbo.d2_vacacion')
            ->whereIn('id_emp', $ids)
            ->where('estado_permiso', 'APROBADO')
            ->where('fecha_inicial', '<=', $request->fecha_hasta)
            ->where('fecha_final',   '>=', $request->fecha_desde)
            ->select('id_emp', DB::raw('DATE(fecha_inicial) as fecha_inicial'), DB::raw('DATE(fecha_final) as fecha_final'))
            ->get()
            ->groupBy('id_emp');

        // Cruzar empleados × fechas
        $resultado = [];
        foreach ($empleados as $emp) {
            $vacsEmp = $vacaciones[$emp->id_emp] ?? collect();
            foreach ($fechas as $fecha) {
                $marc = $marcaciones[$emp->id_emp][$fecha] ?? null;
                $entrada  = $marc ? (int)$marc->tiene_entrada  : 0;
                $salLunch = $marc ? (int)$marc->tiene_sal_lunch : 0;
                $entLunch = $marc ? (int)$marc->tiene_ent_lunch : 0;
                $salida   = $marc ? (int)$marc->tiene_salida    : 0;

                // Solo incluir si falta al menos una marcación
                if ($entrada >= 1 && $salLunch >= 1 && $entLunch >= 1 && $salida >= 1) continue;

                // Verificar si el empleado tiene vacaciones aprobadas que cubran esta fecha
                $enVacaciones = false;
                foreach ($vacsEmp as $vac) {
                    if ($fecha >= $vac->fecha_inicial && $fecha <= $vac->fecha_final) {
                        $enVacaciones = true;
                        break;
                    }
                }

                $resultado[] = [
                    'fecha'          => $fecha,
                    'id_emp'         => $emp->id_emp,
                    'nombre_completo'=> trim($emp->apellido_emp) . ' ' . trim($emp->nombre_emp),
                    'nombre_depto'   => $emp->nombre_depto,
                    'tiene_entrada'  => $entrada,
                    'tiene_sal_lunch'=> $salLunch,
                    'tiene_ent_lunch'=> $entLunch,
                    'tiene_salida'   => $salida,
                    'motivo'         => $enVacaciones ? 'VACACIONES' : null,
                ];
            }
        }

        $formato = $request->get('formato');
        if ($formato === 'excel') return $this->exportarFaltantesExcel($resultado, $request);
        if ($formato === 'pdf')   return $this->exportarFaltantesPdf($resultado, $request);
        return response()->json($resultado);
    }

    private function exportarFaltantesExcel($datos, $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Marc. No Realizadas');

        $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';

        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', strtoupper($nombreInst));
        $sheet->mergeCells('A2:G2');
        $sheet->setCellValue('A2', 'REPORTE DE MARCACIONES NO REALIZADAS');
        $sheet->mergeCells('A3:G3');
        $sheet->setCellValue('A3', 'Período: ' . $request->fecha_desde . ' al ' . $request->fecha_hasta);

        foreach (['A1','A2','A3'] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true);
            $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getStyle('A1')->getFont()->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(11);

        $cabeceras = ['Fecha','Empleado','Departamento','Entrada','Sal. Lunch','Ent. Lunch','Salida'];
        $sheet->fromArray($cabeceras, null, 'A5');
        $sheet->getStyle('A5:G5')->getFont()->setBold(true);
        $sheet->getStyle('A5:G5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B5447');
        $sheet->getStyle('A5:G5')->getFont()->getColor()->setARGB('FFFFFFFF');

        $celda = fn($v, $motivo) => $v ? '✓' : ($motivo === 'VACACIONES' ? 'Vacaciones' : 'No registró');
        $fila = 6;
        foreach ($datos as $r) {
            $mot = $r['motivo'] ?? null;
            $sheet->fromArray([
                $r['fecha'],
                $r['nombre_completo'],
                $r['nombre_depto'],
                $celda($r['tiene_entrada'],   $mot),
                $celda($r['tiene_sal_lunch'], $mot),
                $celda($r['tiene_ent_lunch'], $mot),
                $celda($r['tiene_salida'],    $mot),
            ], null, "A{$fila}");
            if ($fila % 2 === 0) {
                $sheet->getStyle("A{$fila}:G{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF4FBF8');
            }
            $fila++;
        }

        foreach (range('A', 'G') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        ob_start(); $writer->save('php://output'); $content = ob_get_clean();
        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="marcaciones_no_realizadas_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.xlsx"',
        ]);
    }

    private function exportarFaltantesPdf($datos, $request)
    {
        $logo        = base64_encode(file_get_contents(public_path('logo.png')));
        $nombreInst  = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
        $generadoPor = trim($request->user()->apellido_emp) . ' ' . trim($request->user()->nombre_emp);

        $pdf = Pdf::loadView('reportes.reporte_faltantes', compact('datos', 'logo', 'nombreInst', 'generadoPor', 'request'))
            ->setPaper('a4', 'landscape');
        return $pdf->download('marcaciones_no_realizadas_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.pdf');
    }

    // Reporte 3: Movimientos de Personal
    public function movimientosPersonal(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
        ]);

        $tipos = $request->get('tipos', 'VACACIONES,PERMISO,LICENCIA,COMISION');
        if (!is_array($tipos)) $tipos = explode(',', $tipos);
        $tipos = array_map('trim', $tipos);

        // Normalizar: aceptar con o sin tilde
        $tipos = array_map(fn($t) => str_replace('COMISIÓN', 'COMISION', $t), $tipos);

        $resultado = collect();

        // Closure que aplica filtros comunes a cualquier query
        $applyFilters = function ($q) use ($request) {
            if ($request->filled('id_depto')) {
                $q->where('e.id_depto', $request->id_depto);
            }
            if ($request->filled('id_emp')) {
                $b = '%' . $request->id_emp . '%';
                $q->whereRaw("(e.identificacion ILIKE ? OR e.apellido_emp ILIKE ? OR e.nombre_emp ILIKE ?)", [$b, $b, $b]);
            }
            return $q;
        };

        // VACACIONES
        if (in_array('VACACIONES', $tipos)) {
            $q = DB::table('dbo.d2_vacacion as v')
                ->join('dbo.ad_empleado as e', 'e.id_emp', '=', 'v.id_emp')
                ->join('dbo.ad_departamento as d', 'd.id_depto', '=', 'e.id_depto')
                ->where('v.estado_permiso', 'APROBADO')
                ->whereDate('v.fecha_inicial', '>=', $request->fecha_desde)
                ->whereDate('v.fecha_inicial', '<=', $request->fecha_hasta)
                ->where('e.id_depto', '!=', 999);
            $applyFilters($q);
            $rows = $q->select(
                DB::raw("'VACACIONES' as tipo"),
                'e.id_emp',
                DB::raw("trim(e.apellido_emp) || ' ' || trim(e.nombre_emp) as nombre_completo"),
                'd.nombre_depto',
                'e.cargo_empleado',
                DB::raw("v.fecha_inicial::date as fecha_desde"),
                DB::raw("v.fecha_final::date as fecha_hasta"),
                DB::raw("(v.fecha_final::date - v.fecha_inicial::date + 1) as dias"),
                DB::raw("'Vacaciones aprobadas' as detalle")
            )->get();
            $resultado = $resultado->concat($rows);
        }

        // PERMISOS con descuento
        if (in_array('PERMISO', $tipos)) {
            $q = DB::table('dbo.d2_permiso as p')
                ->join('dbo.ad_empleado as e', 'e.id_emp', '=', 'p.id_emp')
                ->join('dbo.ad_departamento as d', 'd.id_depto', '=', 'e.id_depto')
                ->where('p.estado_permiso', 'APROBADO')
                ->where('p.descontable', 'SI')
                ->whereDate('p.fecha_desde', '>=', $request->fecha_desde)
                ->whereDate('p.fecha_desde', '<=', $request->fecha_hasta)
                ->where('e.id_depto', '!=', 999);
            $applyFilters($q);
            $rows = $q->select(
                DB::raw("'PERMISO' as tipo"),
                'e.id_emp',
                DB::raw("trim(e.apellido_emp) || ' ' || trim(e.nombre_emp) as nombre_completo"),
                'd.nombre_depto',
                'e.cargo_empleado',
                DB::raw("p.fecha_desde::date as fecha_desde"),
                DB::raw("p.fecha_hasta::date as fecha_hasta"),
                DB::raw("CASE WHEN p.todo_dia='SI' THEN (p.fecha_hasta::date - p.fecha_desde::date + 1) ELSE NULL END as dias"),
                'p.razon as detalle'
            )->get();
            $resultado = $resultado->concat($rows);
        }

        // LICENCIAS (permisos sin descuento)
        if (in_array('LICENCIA', $tipos)) {
            $q = DB::table('dbo.d2_permiso as p')
                ->join('dbo.ad_empleado as e', 'e.id_emp', '=', 'p.id_emp')
                ->join('dbo.ad_departamento as d', 'd.id_depto', '=', 'e.id_depto')
                ->where('p.estado_permiso', 'APROBADO')
                ->where('p.descontable', 'NO')
                ->whereDate('p.fecha_desde', '>=', $request->fecha_desde)
                ->whereDate('p.fecha_desde', '<=', $request->fecha_hasta)
                ->where('e.id_depto', '!=', 999);
            $applyFilters($q);
            $rows = $q->select(
                DB::raw("'LICENCIA' as tipo"),
                'e.id_emp',
                DB::raw("trim(e.apellido_emp) || ' ' || trim(e.nombre_emp) as nombre_completo"),
                'd.nombre_depto',
                'e.cargo_empleado',
                DB::raw("p.fecha_desde::date as fecha_desde"),
                DB::raw("p.fecha_hasta::date as fecha_hasta"),
                DB::raw("CASE WHEN p.todo_dia='SI' THEN (p.fecha_hasta::date - p.fecha_desde::date + 1) ELSE NULL END as dias"),
                'p.razon as detalle'
            )->get();
            $resultado = $resultado->concat($rows);
        }

        // COMISIONES
        if (in_array('COMISION', $tipos)) {
            $q = DB::table('dbo.vac_liquidacion_historico as l')
                ->join('dbo.ad_empleado as e', 'e.id_emp', '=', 'l.id_emp')
                ->join('dbo.ad_departamento as d', 'd.id_depto', '=', 'e.id_depto')
                ->whereIn('l.motivo', ['INICIO_COMISION', 'FIN_COMISION_SALIDA'])
                ->whereDate('l.fecha_evento', '>=', $request->fecha_desde)
                ->whereDate('l.fecha_evento', '<=', $request->fecha_hasta)
                ->where('e.id_depto', '!=', 999);
            $applyFilters($q);
            $rows = $q->select(
                DB::raw("'COMISION' as tipo"),
                'e.id_emp',
                DB::raw("trim(e.apellido_emp) || ' ' || trim(e.nombre_emp) as nombre_completo"),
                'd.nombre_depto',
                'e.cargo_empleado',
                DB::raw("l.fecha_evento::date as fecha_desde"),
                DB::raw("l.fecha_evento::date as fecha_hasta"),
                DB::raw("NULL::integer as dias"),
                'l.motivo as detalle'
            )->get();
            $resultado = $resultado->concat($rows);
        }

        // Ordenar en PHP por fecha y nombre
        $resultado = $resultado->sortBy([
            ['fecha_desde', 'asc'],
            ['nombre_completo', 'asc'],
        ])->values();

        $formato = $request->get('formato');
        if ($formato === 'excel') return $this->exportarMovimientosExcel($resultado, $request);
        if ($formato === 'pdf')   return $this->exportarMovimientosPdf($resultado, $request);
        return response()->json($resultado);
    }

    private function exportarMovimientosExcel($datos, $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Movimientos');

        $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', strtoupper($nombreInst));
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'REPORTE DE MOVIMIENTOS DE PERSONAL');
        $sheet->mergeCells('A3:H3');
        $sheet->setCellValue('A3', 'Período: ' . $request->fecha_desde . ' al ' . $request->fecha_hasta);

        foreach (['A1','A2','A3'] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true);
            $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getStyle('A1')->getFont()->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(11);

        $cabeceras = ['Tipo','Empleado','Cargo','Departamento','Fecha Desde','Fecha Hasta','Días','Detalle'];
        $sheet->fromArray($cabeceras, null, 'A5');
        $sheet->getStyle('A5:H5')->getFont()->setBold(true);
        $sheet->getStyle('A5:H5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B5447');
        $sheet->getStyle('A5:H5')->getFont()->getColor()->setARGB('FFFFFFFF');

        $coloresTipo = [
            'VACACIONES' => 'FFD1FAE5',
            'PERMISO'    => 'FFFEF9C3',
            'LICENCIA'   => 'FFE0E7FF',
            'COMISION'   => 'FFFCE7F3',
        ];

        $fila = 6;
        foreach ($datos as $r) {
            $sheet->fromArray([
                $r->tipo,
                $r->nombre_completo,
                $r->cargo_empleado ?? '—',
                $r->nombre_depto,
                $r->fecha_desde,
                $r->fecha_hasta,
                $r->dias ?? '—',
                $r->detalle,
            ], null, "A{$fila}");
            $color = $coloresTipo[$r->tipo] ?? 'FFFFFFFF';
            $sheet->getStyle("A{$fila}:H{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
            $fila++;
        }

        foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        ob_start(); $writer->save('php://output'); $content = ob_get_clean();
        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="movimientos_personal_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.xlsx"',
        ]);
    }

    private function exportarMovimientosPdf($datos, $request)
    {
        $logo        = base64_encode(file_get_contents(public_path('logo.png')));
        $nombreInst  = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
        $generadoPor = trim($request->user()->apellido_emp) . ' ' . trim($request->user()->nombre_emp);

        $pdf = Pdf::loadView('reportes.reporte_movimientos', compact('datos', 'logo', 'nombreInst', 'generadoPor', 'request'))
            ->setPaper('a4', 'landscape');
        return $pdf->download('movimientos_personal_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.pdf');
    }
}
