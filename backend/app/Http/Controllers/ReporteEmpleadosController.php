<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ReporteEmpleadosController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];

    // Antes sin ningún control de rol — index() expone campos sociales/de salud
    // sensibles (discapacidad, enfermedad catastrófica, grupo vulnerable, persona
    // sustituta) de todos los empleados a cualquier autenticado. Cerrado 2026-09-03.
    public function resumen(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);

        $hoy   = now()->toDateString();
        $en30  = now()->addDays(30)->toDateString();
        $hace5 = now()->subYears(5)->toDateString();

        $base = DB::table('dbo.ad_empleado')
            ->where('estado', 'ACTIVO')
            ->where('id_depto', '!=', 999);

        return response()->json([
            'total_activos' => (clone $base)->count(),
            'alertas' => [
                'sercop_vencido'    => (clone $base)->whereNotNull('fecha_vence_sercop')->where('fecha_vence_sercop', '<', $hoy)->count(),
                'sercop_proximo'    => (clone $base)->whereNotNull('fecha_vence_sercop')->whereBetween('fecha_vence_sercop', [$hoy, $en30])->count(),
                'sustituta_vencida' => (clone $base)->whereNotNull('sustituta_fecha_caducidad')->where('sustituta_fecha_caducidad', '<', $hoy)->count(),
                'sustituta_proxima' => (clone $base)->whereNotNull('sustituta_fecha_caducidad')->whereBetween('sustituta_fecha_caducidad', [$hoy, $en30])->count(),
                'guarderia'         => DB::table('dbo.ad_empleado_hijo as h')
                    ->join('dbo.ad_empleado as e', 'h.id_emp', '=', 'e.id_emp')
                    ->where('e.estado', 'ACTIVO')->where('e.id_depto', '!=', 999)
                    ->where('h.fecha_nacimiento', '>', $hace5)->whereNotNull('h.fecha_nacimiento')
                    ->distinct()->count('h.id_emp'),
            ],
            'stats' => [
                'por_sexo' => DB::table('dbo.ad_empleado')
                    ->where('estado', 'ACTIVO')->where('id_depto', '!=', 999)
                    ->selectRaw("COALESCE(sexo, 'NO ESP.') as label, COUNT(*) as total")
                    ->groupByRaw("COALESCE(sexo, 'NO ESP.')")->orderByDesc('total')->get(),
                'por_contrato' => DB::table('dbo.ad_empleado')
                    ->where('estado', 'ACTIVO')->where('id_depto', '!=', 999)
                    ->selectRaw("COALESCE(TRIM(tipo_contrato), 'SIN CONTRATO') as label, COUNT(*) as total")
                    ->groupByRaw("COALESCE(TRIM(tipo_contrato), 'SIN CONTRATO')")->orderByDesc('total')->get(),
                'por_modalidad' => DB::table('dbo.ad_empleado')
                    ->where('estado', 'ACTIVO')->where('id_depto', '!=', 999)
                    ->selectRaw("COALESCE(modalidad_marcacion, 'NO ESP.') as label, COUNT(*) as total")
                    ->groupByRaw("COALESCE(modalidad_marcacion, 'NO ESP.')")->orderByDesc('total')->get(),
                'por_antiguedad' => DB::table('dbo.ad_empleado')
                    ->where('estado', 'ACTIVO')->where('id_depto', '!=', 999)
                    ->whereNotNull('fecha_ingreso')
                    ->selectRaw("
                        CASE
                            WHEN FLOOR(DATE_PART('year', AGE(NOW(), fecha_ingreso::date))) < 5  THEN 'Menos de 5'
                            WHEN FLOOR(DATE_PART('year', AGE(NOW(), fecha_ingreso::date))) < 10 THEN '5 - 10'
                            WHEN FLOOR(DATE_PART('year', AGE(NOW(), fecha_ingreso::date))) < 15 THEN '10 - 15'
                            WHEN FLOOR(DATE_PART('year', AGE(NOW(), fecha_ingreso::date))) < 20 THEN '15 - 20'
                            ELSE '20 o más'
                        END as label,
                        COUNT(*) as total,
                        MIN(FLOOR(DATE_PART('year', AGE(NOW(), fecha_ingreso::date)))) as min_anios
                    ")
                    ->groupByRaw("
                        CASE
                            WHEN FLOOR(DATE_PART('year', AGE(NOW(), fecha_ingreso::date))) < 5  THEN 'Menos de 5'
                            WHEN FLOOR(DATE_PART('year', AGE(NOW(), fecha_ingreso::date))) < 10 THEN '5 - 10'
                            WHEN FLOOR(DATE_PART('year', AGE(NOW(), fecha_ingreso::date))) < 15 THEN '10 - 15'
                            WHEN FLOOR(DATE_PART('year', AGE(NOW(), fecha_ingreso::date))) < 20 THEN '15 - 20'
                            ELSE '20 o más'
                        END
                    ")
                    ->orderBy('min_anios')
                    ->get(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);

        // Límite de seguridad — antes ->get() sin tope. No es paginación real de UI
        // (el frontend pagina client-side sobre el array completo); si el volumen algún
        // día lo justifica, cambiar a paginate() requiere también tocar la vista.
        $empleados = $this->buildQuery($request)->limit(5000)->get();
        $empleados = $this->enriquecerHijos($empleados);

        if ($request->formato === 'excel') return $this->exportExcel($empleados, $request);
        if ($request->formato === 'pdf')   return $this->exportPdf($empleados, $request);

        return response()->json($empleados->values());
    }

    private function buildQuery(Request $request)
    {
        $hoy   = now()->toDateString();
        $en30  = now()->addDays(30)->toDateString();
        $hace5 = now()->subYears(5)->toDateString();

        $q = DB::table('dbo.ad_empleado as e')
            ->leftJoin('dbo.ad_departamento as d',             'e.id_depto',                  '=', 'd.id_depto')
            ->leftJoin('dbo.ad_grupo_vulnerable as gv',        'e.grupo_vulnerable_id',        '=', 'gv.id')
            ->leftJoin('dbo.ad_grupo_prioritario as gp',       'e.grupo_prioritario_id',       '=', 'gp.id')
            ->leftJoin('dbo.ad_tipo_discapacidad as td',       'e.tipo_discapacidad_id',       '=', 'td.id')
            ->leftJoin('dbo.ad_enfermedad_catastrofica as ec', 'e.enfermedad_catastrofica_id', '=', 'ec.id')
            ->where('e.id_depto', '!=', 999)
            ->select([
                'e.id_emp', 'e.identificacion', 'e.apellido_emp', 'e.nombre_emp',
                'e.cargo_empleado', 'e.estado', 'e.tipo_contrato', 'e.modalidad_laboral',
                'e.modalidad_marcacion', 'e.sexo', 'e.tipo_sangre',
                'e.tiene_discapacidad', 'e.porcentaje_discapacidad',
                'e.tiene_enfermedad_catastrofica', 'e.tiene_persona_sustituta',
                'e.sustituta_fecha_caducidad', 'e.num_sercop', 'e.fecha_vence_sercop',
                'e.num_hijos_mayores', 'e.puede_solicitar_vehiculo', 'e.fecha_ingreso',
                'e.motivo_salida', 'e.motivo_reactivacion', 'e.institucion_comision', 'e.es_comisionado_entrante',
                'd.nombre_depto',
                'gv.nombre as grupo_vulnerable',
                'gp.nombre as grupo_prioritario',
                'td.nombre as tipo_discapacidad',
                'ec.nombre as enfermedad_catastrofica',
                DB::raw("FLOOR(DATE_PART('year', AGE(NOW(), e.fecha_ingreso::date))) as anios_servicio"),
            ]);

        $estado = $request->get('estado', 'ACTIVO');
        if ($estado && $estado !== 'TODOS') $q->where('e.estado', $estado);

        if ($request->filled('id_depto'))         $q->where('e.id_depto', $request->id_depto);
        if ($request->filled('busqueda')) {
            $like = '%' . $request->busqueda . '%';
            $q->where(fn($w) => $w->whereRaw("(e.apellido_emp || ' ' || e.nombre_emp) ILIKE ?", [$like])->orWhere('e.identificacion', 'ILIKE', $like));
        }
        if ($request->filled('tipo_contrato'))        $q->whereRaw('TRIM(e.tipo_contrato) = ?', [$request->tipo_contrato]);
        if ($request->filled('modalidad_laboral'))    $q->where('e.modalidad_laboral',   $request->modalidad_laboral);
        if ($request->filled('modalidad_marcacion'))  $q->where('e.modalidad_marcacion', $request->modalidad_marcacion);
        if ($request->filled('sexo'))                 $q->where('e.sexo',                $request->sexo);
        if ($request->filled('tipo_sangre'))          $q->where('e.tipo_sangre',         $request->tipo_sangre);
        if ($request->filled('grupo_vulnerable_id'))  $q->where('e.grupo_vulnerable_id', $request->grupo_vulnerable_id);
        if ($request->filled('grupo_prioritario_id')) $q->where('e.grupo_prioritario_id',$request->grupo_prioritario_id);
        if ($request->filled('tiene_discapacidad'))   $q->where('e.tiene_discapacidad',  $request->tiene_discapacidad === '1');
        if ($request->filled('tiene_enfermedad'))     $q->where('e.tiene_enfermedad_catastrofica', $request->tiene_enfermedad === '1');
        if ($request->filled('puede_vehiculo'))       $q->where('e.puede_solicitar_vehiculo', $request->puede_vehiculo === '1');
        if ($request->filled('motivo_salida'))         $q->where('e.motivo_salida', $request->motivo_salida);
        if ($request->filled('es_comisionado_entrante')) $q->where('e.es_comisionado_entrante', $request->es_comisionado_entrante === '1');

        if ($request->filled('antiguedad')) {
            switch ($request->antiguedad) {
                case 'menos5': $q->whereRaw("FLOOR(DATE_PART('year', AGE(NOW(), e.fecha_ingreso::date))) < 5"); break;
                case '5a10':   $q->whereRaw("FLOOR(DATE_PART('year', AGE(NOW(), e.fecha_ingreso::date))) >= 5  AND FLOOR(DATE_PART('year', AGE(NOW(), e.fecha_ingreso::date))) < 10"); break;
                case '10a15':  $q->whereRaw("FLOOR(DATE_PART('year', AGE(NOW(), e.fecha_ingreso::date))) >= 10 AND FLOOR(DATE_PART('year', AGE(NOW(), e.fecha_ingreso::date))) < 15"); break;
                case '15a20':  $q->whereRaw("FLOOR(DATE_PART('year', AGE(NOW(), e.fecha_ingreso::date))) >= 15 AND FLOOR(DATE_PART('year', AGE(NOW(), e.fecha_ingreso::date))) < 20"); break;
                case 'mas20':  $q->whereRaw("FLOOR(DATE_PART('year', AGE(NOW(), e.fecha_ingreso::date))) >= 20"); break;
            }
        }

        switch ($request->sercop_filter) {
            case 'vencido': $q->whereNotNull('e.fecha_vence_sercop')->where('e.fecha_vence_sercop', '<', $hoy); break;
            case 'proximo': $q->whereNotNull('e.fecha_vence_sercop')->whereBetween('e.fecha_vence_sercop', [$hoy, $en30]); break;
            case 'sin':     $q->where(fn($w) => $w->whereNull('e.num_sercop')->orWhere('e.num_sercop', '')); break;
            case 'con':     $q->whereNotNull('e.num_sercop')->where('e.num_sercop', '!=', ''); break;
        }

        switch ($request->sustituta_filter) {
            case 'vencida': $q->whereNotNull('e.sustituta_fecha_caducidad')->where('e.sustituta_fecha_caducidad', '<', $hoy); break;
            case 'proxima': $q->whereNotNull('e.sustituta_fecha_caducidad')->whereBetween('e.sustituta_fecha_caducidad', [$hoy, $en30]); break;
            case 'tiene':   $q->where('e.tiene_persona_sustituta', true); break;
        }

        if ($request->con_guarderia == '1') {
            $q->whereExists(function ($sub) use ($hace5) {
                $sub->select(DB::raw(1))
                    ->from('dbo.ad_empleado_hijo as h')
                    ->whereColumn('h.id_emp', 'e.id_emp')
                    ->where('h.fecha_nacimiento', '>', $hace5)
                    ->whereNotNull('h.fecha_nacimiento');
            });
        }

        return $q->orderBy('d.nombre_depto')->orderBy('e.apellido_emp');
    }

    private function enriquecerHijos($empleados)
    {
        if ($empleados->isEmpty()) return $empleados;
        $hace5  = now()->subYears(5)->toDateString();
        $counts = DB::table('dbo.ad_empleado_hijo')
            ->whereIn('id_emp', $empleados->pluck('id_emp'))
            ->where('fecha_nacimiento', '>', $hace5)
            ->whereNotNull('fecha_nacimiento')
            ->selectRaw('id_emp, COUNT(*) as total')
            ->groupBy('id_emp')
            ->pluck('total', 'id_emp');
        return $empleados->map(fn($e) => (object) array_merge(
            (array) $e,
            ['hijos_menores_5' => (int) ($counts[$e->id_emp] ?? 0)]
        ));
    }

    private function etiquetaTitulo(?string $antiguedad): string
    {
        return match($antiguedad) {
            'menos5' => 'NÓMINA DE PERSONAL — MENOS DE 5 AÑOS DE SERVICIO',
            '5a10'   => 'NÓMINA DE PERSONAL — 5 A 10 AÑOS DE SERVICIO',
            '10a15'  => 'NÓMINA DE PERSONAL — 10 A 15 AÑOS DE SERVICIO',
            '15a20'  => 'NÓMINA DE PERSONAL — 15 A 20 AÑOS DE SERVICIO',
            'mas20'  => 'NÓMINA DE PERSONAL — 20 O MÁS AÑOS DE SERVICIO',
            default  => 'NÓMINA DE PERSONAL',
        };
    }

    private function exportExcel($empleados, $request)
    {
        $titulo      = $this->etiquetaTitulo($request->antiguedad);
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet()->setTitle('Personal');

        $headers = [
            'N°','Cédula','Apellidos','Nombres','Departamento','Cargo','Estado',
            'Tipo Contrato','Modalidad Laboral','Modalidad Marcación',
            'Sexo','Tipo Sangre',
            'Discapacidad','% Discap.','Tipo Discapacidad',
            'Enf. Catastrófica','Grupo Vulnerable','Grupo Prioritario',
            'Pers. Sustituta','Vence Doc. Sustituta',
            'N° SERCOP','Vigencia SERCOP',
            'Hijos Mayores','Hijos < 5 años',
            'Sol. Vehículo','Fecha Ingreso','Años Servicio',
        ];
        $totalCols = count($headers);
        $lastCol   = Coordinate::stringFromColumnIndex($totalCols);

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0b5447']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];

        // Fila 1: institución
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'CONSEJO DE COMUNICACIÓN');
        $sheet->getStyle('A1')->applyFromArray(array_merge($headerStyle, ['font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']]]));
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Fila 2: título dinámico
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', strtoupper($titulo) . ' — Generado: ' . now()->format('d/m/Y H:i'));
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a8a6f']],
        ]);

        // Fila 4: encabezados de columna
        foreach ($headers as $idx => $h) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue($col . '4', $h);
        }
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray($headerStyle);
        $sheet->getRowDimension(4)->setRowHeight(28);

        // Datos
        $row = 5;
        $nro = 1;
        foreach ($empleados as $e) {
            $bg   = ($nro % 2 === 0) ? 'edf7f4' : 'ffffff';
            $data = [
                $nro++,
                $e->identificacion,
                $e->apellido_emp,
                $e->nombre_emp,
                $e->nombre_depto ?? '',
                $e->cargo_empleado ?? '',
                $e->estado,
                trim($e->tipo_contrato ?? ''),
                $e->modalidad_laboral ?? '',
                $e->modalidad_marcacion ?? '',
                $e->sexo ?? '',
                $e->tipo_sangre ?? '',
                $e->tiene_discapacidad ? 'Sí' : 'No',
                $e->porcentaje_discapacidad ?? '',
                $e->tipo_discapacidad ?? '',
                $e->tiene_enfermedad_catastrofica ? 'Sí' : 'No',
                $e->grupo_vulnerable ?? '',
                $e->grupo_prioritario ?? '',
                $e->tiene_persona_sustituta ? 'Sí' : 'No',
                $e->sustituta_fecha_caducidad ?? '',
                $e->num_sercop ?? '',
                $e->fecha_vence_sercop ?? '',
                $e->num_hijos_mayores ?? 0,
                $e->hijos_menores_5 ?? 0,
                $e->puede_solicitar_vehiculo ? 'Sí' : 'No',
                $e->fecha_ingreso ?? '',
                $e->anios_servicio ?? '',
            ];
            foreach ($data as $idx => $val) {
                $col = Coordinate::stringFromColumnIndex($idx + 1);
                $sheet->setCellValue($col . $row, $val);
            }
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            ]);
            $row++;
        }

        // Fila total
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", 'Total: ' . ($nro - 1) . ' empleado(s)');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e8f5f2']],
        ]);

        // Auto-width
        for ($i = 1; $i <= $totalCols; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        ob_start();
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $content = ob_get_clean();

        $nombre = 'nomina_personal_' . now()->format('Ymd_His') . '.xlsx';
        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }

    private function exportPdf($empleados, $request)
    {
        $logo        = base64_encode(file_get_contents(public_path('logo.png')));
        $nombreInst  = 'CONSEJO DE COMUNICACIÓN';
        $generadoPor = trim($request->user()->apellido_emp) . ' ' . trim($request->user()->nombre_emp);
        $titulo      = $this->etiquetaTitulo($request->antiguedad);

        $pdf = Pdf::loadView('reportes.reporte_empleados', compact('empleados', 'logo', 'nombreInst', 'generadoPor', 'titulo'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('nomina_personal_' . now()->format('Ymd') . '.pdf');
    }
}
