<?php
namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteTransporteController extends Controller
{
    private const ROLES_TRANSPORTE = ['ADMINISTRADOR', 'TRANSPORTE'];

    // ─── VALES DE COMBUSTIBLE ─────────────────────────────────────────────────

    public function vales(Request $request)
    {
        $this->requireRole($request, self::ROLES_TRANSPORTE);
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
        ]);

        $query = DB::table('dbo.trans_vale_combustible as v')
            ->join('dbo.trans_vehiculo as veh', 'veh.id', '=', 'v.vehiculo_id')
            ->join('dbo.ad_empleado as e', 'e.id_emp', '=', 'v.id_emp_conductor')
            ->whereBetween('v.fecha', [$request->fecha_desde, $request->fecha_hasta])
            ->select(
                'v.id', 'v.numero', 'v.fecha', 'v.gasolinera', 'v.estado',
                'veh.placa', 'veh.marca', 'veh.modelo',
                DB::raw("trim(e.apellido_emp) || ' ' || trim(e.nombre_emp) as conductor"),
                'v.glns_extra', 'v.pu_extra', 'v.valor_extra',
                'v.glns_super', 'v.pu_super', 'v.valor_super',
                'v.glns_diesel', 'v.pu_diesel', 'v.valor_diesel',
                DB::raw("COALESCE(v.valor_extra,0) + COALESCE(v.valor_super,0) + COALESCE(v.valor_diesel,0) as valor_total")
            );

        if ($request->filled('vehiculo_id'))       $query->where('v.vehiculo_id', $request->vehiculo_id);
        if ($request->filled('id_emp_conductor'))  $query->where('v.id_emp_conductor', $request->id_emp_conductor);
        if ($request->filled('estado'))            $query->where('v.estado', $request->estado);

        $datos = $query->orderBy('v.fecha', 'desc')->orderBy('v.numero', 'desc')->get();

        $vigentes = $datos->where('estado', '!=', 'ANULADO');
        $resumen = [
            'total_vales'       => $datos->count(),
            'total_anulados'    => $datos->where('estado', 'ANULADO')->count(),
            'total_valor'       => round($vigentes->sum('valor_total'), 2),
            'total_glns_extra'  => round($vigentes->sum('glns_extra'), 2),
            'total_glns_super'  => round($vigentes->sum('glns_super'), 2),
            'total_glns_diesel' => round($vigentes->sum('glns_diesel'), 2),
        ];

        $formato = $request->get('formato');
        if ($formato === 'excel') return $this->exportarValesExcel($datos, $resumen, $request);
        if ($formato === 'pdf')   return $this->exportarValesPdf($datos, $resumen, $request);
        return response()->json(['datos' => $datos, 'resumen' => $resumen]);
    }

    private function exportarValesExcel($datos, $resumen, $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vales Combustible');

        $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';

        $sheet->mergeCells('A1:M1');
        $sheet->setCellValue('A1', strtoupper($nombreInst));
        $sheet->mergeCells('A2:M2');
        $sheet->setCellValue('A2', 'REPORTE DE VALES DE COMBUSTIBLE');
        $sheet->mergeCells('A3:M3');
        $sheet->setCellValue('A3', 'Período: ' . $request->fecha_desde . ' al ' . $request->fecha_hasta
            . ' — Total: $' . number_format($resumen['total_valor'], 2) . ' (' . $resumen['total_vales'] . ' vales, ' . $resumen['total_anulados'] . ' anulados)');

        foreach (['A1', 'A2', 'A3'] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true);
            $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getStyle('A1')->getFont()->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(11);

        $cabeceras = ['N°', 'Fecha', 'Vehículo', 'Conductor', 'Gasolinera', 'Gl. Extra', 'Gl. Súper', 'Gl. Diésel', 'Val. Extra', 'Val. Súper', 'Val. Diésel', 'Total', 'Estado'];
        $sheet->fromArray($cabeceras, null, 'A5');
        $sheet->getStyle('A5:M5')->getFont()->setBold(true);
        $sheet->getStyle('A5:M5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A5F');
        $sheet->getStyle('A5:M5')->getFont()->getColor()->setARGB('FFFFFFFF');

        $fila = 6;
        foreach ($datos as $r) {
            $sheet->fromArray([
                $r->numero,
                substr($r->fecha, 0, 10),
                $r->placa . ' — ' . $r->marca . ' ' . $r->modelo,
                $r->conductor,
                $r->gasolinera,
                $r->glns_extra ?? 0,
                $r->glns_super ?? 0,
                $r->glns_diesel ?? 0,
                $r->valor_extra ?? 0,
                $r->valor_super ?? 0,
                $r->valor_diesel ?? 0,
                $r->valor_total,
                $r->estado,
            ], null, "A{$fila}");
            if ($fila % 2 === 0) {
                $sheet->getStyle("A{$fila}:M{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF3F7');
            }
            if ($r->estado === 'ANULADO') {
                $sheet->getStyle("A{$fila}:M{$fila}")->getFont()->setStrikethrough(true);
            }
            $fila++;
        }

        foreach (range('A', 'M') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        ob_start(); $writer->save('php://output'); $content = ob_get_clean();
        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="reporte_vales_combustible_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.xlsx"',
        ]);
    }

    private function exportarValesPdf($datos, $resumen, $request)
    {
        $logo        = base64_encode(file_get_contents(public_path('logo.png')));
        $nombreInst  = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
        $generadoPor = trim($request->user()->apellido_emp) . ' ' . trim($request->user()->nombre_emp);

        $pdf = Pdf::loadView('reportes.trans_reporte_vales', compact('datos', 'resumen', 'logo', 'nombreInst', 'generadoPor', 'request'))
            ->setPaper('a4', 'landscape');
        return $pdf->download('reporte_vales_combustible_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.pdf');
    }

    // ─── MOVILIZACIÓN ─────────────────────────────────────────────────────────

    public function movilizacion(Request $request)
    {
        $this->requireRole($request, self::ROLES_TRANSPORTE);
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
        ]);

        $query = DB::table('dbo.trans_solicitud_mov as s')
            ->join('dbo.ad_empleado as sol', 'sol.id_emp', '=', 's.id_emp_solicitante')
            ->leftJoin('dbo.trans_vehiculo as veh', 'veh.id', '=', 's.vehiculo_id')
            ->leftJoin('dbo.ad_empleado as cond', 'cond.id_emp', '=', 's.id_emp_conductor')
            ->whereBetween('s.fecha_movilizacion', [$request->fecha_desde, $request->fecha_hasta])
            ->select(
                's.id', 's.fecha_movilizacion', 's.hora_salida', 's.hora_retorno',
                's.motivo', 's.lugar_destino', 's.estado', 's.km_salida', 's.km_retorno', 's.num_personas',
                DB::raw("trim(sol.apellido_emp) || ' ' || trim(sol.nombre_emp) as solicitante"),
                'veh.placa',
                DB::raw("CASE WHEN cond.id_emp IS NOT NULL THEN trim(cond.apellido_emp) || ' ' || trim(cond.nombre_emp) ELSE NULL END as conductor")
            );

        if ($request->filled('vehiculo_id'))      $query->where('s.vehiculo_id', $request->vehiculo_id);
        if ($request->filled('id_emp_conductor')) $query->where('s.id_emp_conductor', $request->id_emp_conductor);
        if ($request->filled('id_emp_solicitante')) {
            $b = '%' . $request->id_emp_solicitante . '%';
            $query->whereRaw("(sol.identificacion ILIKE ? OR sol.apellido_emp ILIKE ? OR sol.nombre_emp ILIKE ?)", [$b, $b, $b]);
        }
        if ($request->filled('estado')) $query->where('s.estado', $request->estado);

        $datos = $query->orderBy('s.fecha_movilizacion', 'desc')->get()->map(function ($r) {
            $r->km_recorridos = ($r->estado === 'COMPLETADO' && $r->km_salida !== null && $r->km_retorno !== null)
                ? (int) $r->km_retorno - (int) $r->km_salida
                : null;
            return $r;
        });

        $completadas = $datos->where('estado', 'COMPLETADO')->filter(fn($r) => $r->km_recorridos !== null);
        $resumen = [
            'total_solicitudes' => $datos->count(),
            'pendientes'        => $datos->where('estado', 'PENDIENTE')->count(),
            'aprobadas'         => $datos->where('estado', 'APROBADO')->count(),
            'negadas'           => $datos->where('estado', 'NEGADO')->count(),
            'completadas'       => $datos->where('estado', 'COMPLETADO')->count(),
            'km_totales'        => (int) $completadas->sum('km_recorridos'),
            'promedio_km'       => $completadas->count() > 0 ? round($completadas->sum('km_recorridos') / $completadas->count(), 1) : 0,
        ];

        $formato = $request->get('formato');
        if ($formato === 'excel') return $this->exportarMovilizacionExcel($datos, $resumen, $request);
        if ($formato === 'pdf')   return $this->exportarMovilizacionPdf($datos, $resumen, $request);
        return response()->json(['datos' => $datos, 'resumen' => $resumen]);
    }

    private function exportarMovilizacionExcel($datos, $resumen, $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Movilización');

        $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';

        $sheet->mergeCells('A1:K1');
        $sheet->setCellValue('A1', strtoupper($nombreInst));
        $sheet->mergeCells('A2:K2');
        $sheet->setCellValue('A2', 'REPORTE DE MOVILIZACIÓN VEHICULAR');
        $sheet->mergeCells('A3:K3');
        $sheet->setCellValue('A3', 'Período: ' . $request->fecha_desde . ' al ' . $request->fecha_hasta
            . ' — ' . $resumen['total_solicitudes'] . ' solicitudes, ' . $resumen['km_totales'] . ' km recorridos');

        foreach (['A1', 'A2', 'A3'] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true);
            $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getStyle('A1')->getFont()->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(11);

        $cabeceras = ['Fecha', 'Solicitante', 'Vehículo', 'Conductor', 'Destino', 'Motivo', 'H. Salida', 'H. Retorno', 'Km Recorridos', 'N° Personas', 'Estado'];
        $sheet->fromArray($cabeceras, null, 'A5');
        $sheet->getStyle('A5:K5')->getFont()->setBold(true);
        $sheet->getStyle('A5:K5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A5F');
        $sheet->getStyle('A5:K5')->getFont()->getColor()->setARGB('FFFFFFFF');

        $fila = 6;
        foreach ($datos as $r) {
            $sheet->fromArray([
                substr($r->fecha_movilizacion, 0, 10),
                $r->solicitante,
                $r->placa ?? '—',
                $r->conductor ?? '—',
                $r->lugar_destino,
                $r->motivo,
                substr($r->hora_salida, 0, 5),
                substr($r->hora_retorno, 0, 5),
                $r->km_recorridos ?? '—',
                $r->num_personas,
                $r->estado,
            ], null, "A{$fila}");
            if ($fila % 2 === 0) {
                $sheet->getStyle("A{$fila}:K{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF3F7');
            }
            $fila++;
        }

        foreach (range('A', 'K') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        ob_start(); $writer->save('php://output'); $content = ob_get_clean();
        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="reporte_movilizacion_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.xlsx"',
        ]);
    }

    private function exportarMovilizacionPdf($datos, $resumen, $request)
    {
        $logo        = base64_encode(file_get_contents(public_path('logo.png')));
        $nombreInst  = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
        $generadoPor = trim($request->user()->apellido_emp) . ' ' . trim($request->user()->nombre_emp);

        $pdf = Pdf::loadView('reportes.trans_reporte_movilizacion', compact('datos', 'resumen', 'logo', 'nombreInst', 'generadoPor', 'request'))
            ->setPaper('a4', 'landscape');
        return $pdf->download('reporte_movilizacion_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.pdf');
    }

    // ─── MANTENIMIENTO VEHICULAR ──────────────────────────────────────────────

    public function mantenimiento(Request $request)
    {
        $this->requireRole($request, self::ROLES_TRANSPORTE);
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
        ]);

        $query = DB::table('dbo.trans_mantenimiento as m')
            ->join('dbo.trans_vehiculo as veh', 'veh.id', '=', 'm.vehiculo_id')
            ->leftJoin('adq.proveedor as t', 't.id', '=', 'm.taller_id')
            ->leftJoin('dbo.trans_plan_preventivo_cab as p', 'p.id', '=', 'm.plan_preventivo_id')
            ->whereBetween(DB::raw('DATE(m.created_at)'), [$request->fecha_desde, $request->fecha_hasta])
            ->select(
                'm.id', 'm.created_at', 'm.tipo', 'm.descripcion', 'm.estado',
                'm.numero_orden', 'm.fecha_orden', 'm.fecha_finalizacion',
                'm.km_actual', 'm.km_finalizacion',
                'veh.placa', 'veh.marca', 'veh.modelo',
                't.nombre as taller',
                'p.nombre as plan_nombre', 'p.km_hito'
            );

        if ($request->filled('vehiculo_id'))            $query->where('m.vehiculo_id', $request->vehiculo_id);
        if ($request->filled('tipo_mantenimiento_id'))  $query->where('m.tipo_mantenimiento_id', $request->tipo_mantenimiento_id);
        if ($request->filled('estado'))                 $query->where('m.estado', $request->estado);
        if ($request->filled('taller_id'))               $query->where('m.taller_id', $request->taller_id);

        $datos = $query->orderBy('m.created_at', 'desc')->get();

        $resumen = [
            'total'       => $datos->count(),
            'preventivos' => $datos->filter(fn($r) => str_contains($r->tipo, 'PREVENTIVO'))->count(),
            'correctivos' => $datos->filter(fn($r) => str_contains($r->tipo, 'CORRECTIVO'))->count(),
            'finalizados' => $datos->where('estado', 'FINALIZADO')->count(),
            'en_proceso'  => $datos->whereIn('estado', ['PENDIENTE', 'ORDEN_GENERADA', 'EN_TALLER'])->count(),
            'negados'     => $datos->where('estado', 'NEGADO')->count(),
        ];

        $formato = $request->get('formato');
        if ($formato === 'excel') return $this->exportarMantenimientoExcel($datos, $resumen, $request);
        if ($formato === 'pdf')   return $this->exportarMantenimientoPdf($datos, $resumen, $request);
        return response()->json(['datos' => $datos, 'resumen' => $resumen]);
    }

    private function exportarMantenimientoExcel($datos, $resumen, $request)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mantenimiento');

        $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';

        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', strtoupper($nombreInst));
        $sheet->mergeCells('A2:J2');
        $sheet->setCellValue('A2', 'REPORTE DE MANTENIMIENTO VEHICULAR');
        $sheet->mergeCells('A3:J3');
        $sheet->setCellValue('A3', 'Período: ' . $request->fecha_desde . ' al ' . $request->fecha_hasta
            . ' — ' . $resumen['total'] . ' registros (' . $resumen['preventivos'] . ' preventivos, ' . $resumen['correctivos'] . ' correctivos)');

        foreach (['A1', 'A2', 'A3'] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true);
            $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getStyle('A1')->getFont()->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(11);

        $cabeceras = ['Fecha', 'Vehículo', 'Tipo', 'Descripción / Plan', 'Taller', 'N° Orden', 'Km Actual', 'Km Finalización', 'Fecha Fin', 'Estado'];
        $sheet->fromArray($cabeceras, null, 'A5');
        $sheet->getStyle('A5:J5')->getFont()->setBold(true);
        $sheet->getStyle('A5:J5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A5F');
        $sheet->getStyle('A5:J5')->getFont()->getColor()->setARGB('FFFFFFFF');

        $fila = 6;
        foreach ($datos as $r) {
            $sheet->fromArray([
                substr($r->created_at, 0, 10),
                $r->placa . ' — ' . $r->marca . ' ' . $r->modelo,
                $r->tipo,
                $r->plan_nombre ?? $r->descripcion,
                $r->taller ?? '—',
                $r->numero_orden ?? '—',
                $r->km_actual ?? '—',
                $r->km_finalizacion ?? '—',
                $r->fecha_finalizacion ? substr($r->fecha_finalizacion, 0, 10) : '—',
                $r->estado,
            ], null, "A{$fila}");
            if ($fila % 2 === 0) {
                $sheet->getStyle("A{$fila}:J{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF3F7');
            }
            $fila++;
        }

        foreach (range('A', 'J') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        ob_start(); $writer->save('php://output'); $content = ob_get_clean();
        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="reporte_mantenimiento_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.xlsx"',
        ]);
    }

    private function exportarMantenimientoPdf($datos, $resumen, $request)
    {
        $logo        = base64_encode(file_get_contents(public_path('logo.png')));
        $nombreInst  = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto)='nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
        $generadoPor = trim($request->user()->apellido_emp) . ' ' . trim($request->user()->nombre_emp);

        $pdf = Pdf::loadView('reportes.trans_reporte_mantenimiento', compact('datos', 'resumen', 'logo', 'nombreInst', 'generadoPor', 'request'))
            ->setPaper('a4', 'landscape');
        return $pdf->download('reporte_mantenimiento_' . $request->fecha_desde . '_' . $request->fecha_hasta . '.pdf');
    }
}
