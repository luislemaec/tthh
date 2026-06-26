<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReporteAdqController extends Controller
{
    public function kardex(Request $request)
    {
        $request->validate([
            'articulo_id' => 'nullable|exists:pgsql.adq.articulo,id',
            'nivel1'      => 'nullable|string|max:2',
            'nivel2'      => 'nullable|string|max:6',
            'desde'       => 'required|date',
            'hasta'       => 'required|date|after_or_equal:desde',
        ]);

        if (!$request->articulo_id && !$request->nivel1 && !$request->nivel2) {
            return response()->json(['message' => 'Seleccione un artículo o un nivel MEF.'], 422);
        }

        $queryArticulos = DB::table('adq.articulo')->where('estado', 'ACTIVO');
        if ($request->articulo_id) $queryArticulos->where('id', $request->articulo_id);
        if ($request->nivel1)      $queryArticulos->where('nivel1', $request->nivel1);
        if ($request->nivel2)      $queryArticulos->where('nivel2', $request->nivel2);
        $articulos = $queryArticulos->orderBy('nombre')->get();

        $resultados = $articulos->map(function ($articulo) use ($request) {
            $filas = DB::table('adq.kardex')
                ->where('articulo_id', $articulo->id)
                ->whereBetween('fecha', [$request->desde . ' 00:00:00', $request->hasta . ' 23:59:59'])
                ->orderBy('fecha')
                ->orderBy('id')
                ->get();
            return ['articulo' => $articulo, 'filas' => $filas];
        })->filter(fn($r) => $r['filas']->isNotEmpty())->values();

        if ($request->formato === 'pdf') {
            $meses    = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $filename = $request->nivel2 ? "kardex-{$request->nivel2}.pdf"
                      : ($request->nivel1  ? "kardex-nivel-{$request->nivel1}.pdf"
                      : "kardex-{$articulos->first()->codigo}.pdf");
            $pdf = Pdf::loadView('reportes.kardex', compact('resultados', 'meses', 'request'))
                ->setPaper('a4', 'landscape');
            return $pdf->download($filename);
        }

        if ($request->formato === 'excel') {
            return $this->kardexExcel($resultados, $request, $articulos);
        }

        return response()->json($resultados);
    }

    private function kardexExcel($resultados, $request, $articulos)
    {
        $nombreInst = DB::table('dbo.d2_configuracion')
            ->whereRaw("LOWER(concepto) = 'nombre_institucion'")
            ->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';

        $tiposIngreso = ['INGRESO', 'REVERSO_EGRESO', 'AJUSTE_POSITIVO', 'SALDO_INICIAL'];
        $tiposEgreso  = ['EGRESO', 'REVERSO_INGRESO', 'AJUSTE_NEGATIVO'];

        $spreadsheet = new Spreadsheet();

        foreach ($resultados as $index => $item) {
            $art   = $item['articulo'];
            $filas = $item['filas'];
            // Caracteres inválidos para títulos de hoja Excel: \ / ? * [ ] :
            $title = mb_substr(preg_replace('/[\\/\\\\?*\[\]:]/', '', $art->codigo . ' ' . $art->nombre), 0, 31);
            if ($title === '') $title = 'Art' . ($index + 1);

            if ($index === 0) {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle($title);
            } else {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($title);
            }

            // Encabezado
            $sheet->mergeCells('A1:M1'); $sheet->setCellValue('A1', strtoupper($nombreInst));
            $sheet->mergeCells('A2:M2'); $sheet->setCellValue('A2', 'KARDEX DE INVENTARIO NIC 2');
            $sheet->mergeCells('A3:M3'); $sheet->setCellValue('A3', 'Período: ' . $request->desde . ' al ' . $request->hasta);
            $sheet->mergeCells('A4:M4'); $sheet->setCellValue('A4', '[' . $art->codigo . '] ' . $art->nombre);

            foreach (['A1','A2','A3','A4'] as $c) {
                $sheet->getStyle($c)->getFont()->setBold(true);
                $sheet->getStyle($c)->getAlignment()->setHorizontal('center');
            }
            $sheet->getStyle('A1')->getFont()->setSize(12);
            $sheet->getStyle('A2')->getFont()->setSize(11);

            // Encabezados de columna (fila 5 = grupo, fila 6 = sub)
            $headers = [
                'A5' => 'Fecha',      'B5' => 'N° Doc.',   'C5' => 'Tipo',
                'D5' => 'ING Cant.',  'E5' => 'ING P.Unit', 'F5' => 'ING Total',
                'G5' => 'EGR Cant.',  'H5' => 'EGR P.Unit', 'I5' => 'EGR Total',
                'J5' => 'SALDO Cant.','K5' => 'SALDO P.Unit','L5' => 'SALDO Total',
                'M5' => 'Usuario',
            ];
            foreach ($headers as $cell => $val) {
                $sheet->setCellValue($cell, $val);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('4a5e3a');
                $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');
            }

            // Datos
            $row = 6;
            foreach ($filas as $f) {
                $esIngreso = in_array($f->tipo_movimiento, $tiposIngreso);
                $esEgreso  = in_array($f->tipo_movimiento, $tiposEgreso);
                $fecha     = $f->fecha ? Carbon::parse($f->fecha)->format('d/m/Y H:i') : '';

                $sheet->setCellValue("A{$row}", $fecha);
                $sheet->setCellValue("B{$row}", $f->numero_documento ?? '');
                $sheet->setCellValue("C{$row}", $f->tipo_movimiento);
                $sheet->setCellValue("D{$row}", $esIngreso ? (float)$f->cantidad_entrada : '');
                $sheet->setCellValue("E{$row}", $esIngreso ? (float)$f->precio_movimiento : '');
                $sheet->setCellValue("F{$row}", $esIngreso ? round((float)$f->cantidad_entrada * (float)$f->precio_movimiento, 2) : '');
                $sheet->setCellValue("G{$row}", $esEgreso ? (float)$f->cantidad_salida : '');
                $sheet->setCellValue("H{$row}", $esEgreso ? (float)$f->precio_movimiento : '');
                $sheet->setCellValue("I{$row}", $esEgreso ? round((float)$f->cantidad_salida * (float)$f->precio_movimiento, 2) : '');
                $sheet->setCellValue("J{$row}", (float)$f->stock_despues);
                $sheet->setCellValue("K{$row}", (float)$f->precio_despues);
                $sheet->setCellValue("L{$row}", (float)$f->valor_saldo);
                $sheet->setCellValue("M{$row}", $f->usuario ?? '');

                // Fila alterna
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:M{$row}")->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('f5f8f3');
                }
                $row++;
            }

            // Auto-ancho columnas
            foreach (range('A', 'M') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $filename = $request->nivel2 ? "kardex-{$request->nivel2}.xlsx"
                  : ($request->nivel1 ? "kardex-nivel-{$request->nivel1}.xlsx"
                  : 'kardex-' . ($articulos->first()->codigo ?? 'reporte') . '.xlsx');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function libroCompras(Request $request)
    {
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $query = DB::table('adq.orden_compra as oc')
            ->join('adq.proveedor as p', 'p.id', '=', 'oc.proveedor_id')
            ->where('oc.estado', 'RECIBIDO')
            ->where('oc.tipo_documento', 'FACTURA')
            ->whereBetween('oc.fecha_documento', [$request->desde, $request->hasta])
            ->select([
                'oc.id',
                'oc.fecha_documento',
                'oc.numero_documento',
                'oc.proceso_contratacion',
                'oc.subtotal',
                'oc.descuento',
                'oc.iva_valor',
                'oc.total',
                'p.ruc',
                'p.nombre as proveedor_nombre',
            ])
            ->orderBy('oc.fecha_documento')
            ->orderBy('oc.id');

        if ($request->proceso) {
            $query->where('oc.proceso_contratacion', $request->proceso);
        }
        if ($request->filled('proveedor')) {
            $q = $request->proveedor;
            $query->where(function ($q2) use ($q) {
                $q2->where('p.ruc', 'ilike', "%{$q}%")
                   ->orWhere('p.nombre', 'ilike', "%{$q}%");
            });
        }

        $filas = $query->get();

        if ($request->formato === 'pdf') {
            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $pdf = Pdf::loadView('reportes.libro_compras', compact('filas', 'meses', 'request'))
                ->setPaper('a4', 'landscape');
            return $pdf->download("libro-compras.pdf");
        }

        return response()->json($filas);
    }

    public function egresosValorizados(Request $request)
    {
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $query = DB::table('adq.egreso as e')
            ->join('adq.egreso_det as ed', 'ed.egreso_id', '=', 'e.id')
            ->join('adq.articulo as a', 'a.id', '=', 'ed.articulo_id')
            ->where('e.estado', 'DESPACHADO')
            ->whereBetween('e.fecha_despacho', [$request->desde . ' 00:00:00', $request->hasta . ' 23:59:59'])
            ->select([
                'e.numero_secuencial', 'e.anio',
                'e.fecha_despacho', 'e.direccion',
                'e.empleado_nombre',
                'a.codigo as articulo_codigo',
                'a.nombre as articulo_nombre',
                'ed.cantidad',
                'ed.precio_unitario',
                'ed.subtotal',
                'ed.iva_valor',
                'ed.total_linea',
            ])
            ->orderBy('e.fecha_despacho')
            ->orderBy('e.id')
            ->orderBy('ed.id');

        if ($request->filled('direccion')) {
            $query->where('e.direccion', $request->direccion);
        }

        $filas = $query->get();

        if ($request->formato === 'pdf') {
            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $pdf = Pdf::loadView('reportes.egresos_valorizados', compact('filas', 'meses', 'request'))
                ->setPaper('a4', 'landscape');
            return $pdf->download("egresos-valorizados.pdf");
        }

        return response()->json($filas);
    }

    public function inventarioMensual(Request $request)
    {
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $desde = $request->desde;
        $hasta = $request->hasta;

        $tiposIngreso = ['INGRESO', 'REVERSO_EGRESO', 'AJUSTE_POSITIVO'];
        $tiposEgreso  = ['EGRESO',  'REVERSO_INGRESO', 'AJUSTE_NEGATIVO'];
        $todosTipos   = array_merge($tiposIngreso, $tiposEgreso);

        // Paso 1: artículos con movimiento, que tengan nivel2 con asociacion_presupuestaria
        $articuloIds = DB::table('adq.kardex as k')
            ->join('adq.articulo as a', 'k.articulo_id', '=', 'a.id')
            ->join('adq.catalogo_inventario as ci', 'a.nivel2', '=', 'ci.nivel2')
            ->whereNotNull('ci.asociacion_presupuestaria')
            ->whereBetween('k.fecha', ["$desde 00:00:00", "$hasta 23:59:59"])
            ->whereIn('k.tipo_movimiento', $todosTipos)
            ->distinct()
            ->pluck('k.articulo_id')
            ->toArray();

        if (empty($articuloIds)) {
            return response()->json([]);
        }

        // Paso 2: saldo anterior agrupado por partida (primeros 6 chars de asociacion_presupuestaria)
        $idsStr    = implode(',', $articuloIds);
        $saldoRows = DB::select("
            SELECT
                LEFT(ci.asociacion_presupuestaria, 6) as partida,
                SUM(ROUND(k.stock_despues::numeric * k.precio_despues::numeric, 2)) as saldo_anterior
            FROM (
                SELECT DISTINCT ON (k2.articulo_id)
                       k2.articulo_id,
                       k2.stock_despues,
                       k2.precio_despues
                FROM adq.kardex k2
                WHERE k2.articulo_id IN ($idsStr)
                  AND k2.fecha < ?
                ORDER BY k2.articulo_id, k2.fecha DESC, k2.id DESC
            ) k
            JOIN adq.articulo a ON k.articulo_id = a.id
            JOIN adq.catalogo_inventario ci ON ci.nivel2 = a.nivel2
            WHERE ci.asociacion_presupuestaria IS NOT NULL
            GROUP BY LEFT(ci.asociacion_presupuestaria, 6)
        ", ["$desde 00:00:00"]);
        $saldoAnt = collect($saldoRows)->pluck('saldo_anterior', 'partida');

        // Paso 3: movimientos agrupados por partida presupuestaria (primeros 6 chars)
        $movRows = DB::table('adq.kardex as k')
            ->join('adq.articulo as a', 'k.articulo_id', '=', 'a.id')
            ->join('adq.catalogo_inventario as ci', 'a.nivel2', '=', 'ci.nivel2')
            ->leftJoin('adq.orden_compra as oc', function ($join) {
                $join->on('k.referencia_id', '=', 'oc.id')
                     ->where('k.referencia_tipo', '=', 'ORDEN_COMPRA');
            })
            ->whereIn('k.articulo_id', $articuloIds)
            ->whereBetween('k.fecha', ["$desde 00:00:00", "$hasta 23:59:59"])
            ->whereIn('k.tipo_movimiento', $todosTipos)
            ->whereNotNull('ci.asociacion_presupuestaria')
            ->selectRaw("
                LEFT(ci.asociacion_presupuestaria, 6) as partida,
                SUM(CASE
                    WHEN k.tipo_movimiento IN ('INGRESO','REVERSO_EGRESO','AJUSTE_POSITIVO')
                     AND NOT (k.referencia_tipo = 'ORDEN_COMPRA' AND UPPER(oc.proceso_contratacion) LIKE '%CAJA CHICA%')
                    THEN ROUND(k.cantidad_entrada::numeric * k.precio_movimiento::numeric, 2)
                    ELSE 0 END) as ingreso_procesos,
                SUM(CASE
                    WHEN k.tipo_movimiento = 'INGRESO'
                     AND k.referencia_tipo = 'ORDEN_COMPRA'
                     AND UPPER(oc.proceso_contratacion) LIKE '%CAJA CHICA%'
                    THEN ROUND(k.cantidad_entrada::numeric * k.precio_movimiento::numeric, 2)
                    ELSE 0 END) as ingreso_caja_chica,
                SUM(CASE
                    WHEN k.tipo_movimiento IN ('EGRESO','REVERSO_INGRESO','AJUSTE_NEGATIVO')
                    THEN ROUND(k.cantidad_salida::numeric * k.precio_movimiento::numeric, 2)
                    ELSE 0 END) as egreso_mes
            ")
            ->groupByRaw("LEFT(ci.asociacion_presupuestaria, 6)")
            ->get();

        // Paso 4: descripciones — STRING_AGG de catalogo_nivel1 por partida
        // Para cada partida, busca todos los nivel1 distintos en catalogo_inventario
        // cuya asociacion_presupuestaria contiene ese código, y concatena sus descripciones
        $descRows = DB::select("
            SELECT
                t.partida,
                STRING_AGG(cn1.descripcion, ' / ' ORDER BY cn1.descripcion) as descripcion
            FROM (
                SELECT DISTINCT
                    LEFT(ci.asociacion_presupuestaria, 6) as partida,
                    ci.nivel1
                FROM adq.catalogo_inventario ci
                WHERE ci.asociacion_presupuestaria IS NOT NULL
            ) t
            JOIN adq.catalogo_nivel1 cn1 ON cn1.nivel1 = t.nivel1
            GROUP BY t.partida
        ");
        $descripciones = collect($descRows)->pluck('descripcion', 'partida');

        // Paso 5: combinar y calcular saldo final
        $resultado = $movRows->map(function ($row) use ($saldoAnt, $descripciones) {
            $partida  = $row->partida;
            $anterior = round((float)($saldoAnt->get($partida) ?? 0), 2);
            $ingProc  = round((float)$row->ingreso_procesos,   2);
            $ingCaja  = round((float)$row->ingreso_caja_chica, 2);
            $egreso   = round((float)$row->egreso_mes,          2);
            $saldoFin = round($anterior + $ingProc + $ingCaja - $egreso, 2);

            $cuenta = strlen($partida) === 6
                ? substr($partida, 0, 2) . '.' . substr($partida, 2, 2) . '.' . substr($partida, 4, 2)
                : $partida;

            return [
                'partida'            => $partida,
                'cuenta'             => $cuenta,
                'descripcion'        => $descripciones->get($partida) ?? $partida,
                'saldo_anterior'     => $anterior,
                'ingreso_procesos'   => $ingProc,
                'ingreso_caja_chica' => $ingCaja,
                'egreso_mes'         => $egreso,
                'saldo_final'        => $saldoFin,
            ];
        })->sortBy('partida')->values();

        if ($request->formato === 'pdf') {
            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $logoPath   = public_path('logo.png');
            $logo       = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
            $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
            $generadoPor = trim($request->user()->apellido_emp . ' ' . $request->user()->nombre_emp);

            $pdf = Pdf::loadView('reportes.adq_inventario_mensual', compact(
                'resultado', 'meses', 'desde', 'hasta', 'logo', 'nombreInst', 'generadoPor'
            ))->setPaper('a4', 'landscape');

            return $pdf->download("inventario-mensual.pdf");
        }

        if ($request->formato === 'excel') {
            $meses      = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
            $mesNum     = (int) date('n', strtotime($desde));
            $anio       = date('Y', strtotime($desde));
            $periodo    = ($meses[$mesNum] ?? $mesNum) . ' ' . $anio;

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Inventario Mensual');

            // Encabezado
            $sheet->mergeCells('A1:G1'); $sheet->setCellValue('A1', strtoupper($nombreInst));
            $sheet->mergeCells('A2:G2'); $sheet->setCellValue('A2', 'REPORTE DE INVENTARIO MENSUAL');
            $sheet->mergeCells('A3:G3'); $sheet->setCellValue('A3', 'Período: ' . $periodo);
            foreach (['A1','A2','A3'] as $c) {
                $sheet->getStyle($c)->getFont()->setBold(true);
                $sheet->getStyle($c)->getAlignment()->setHorizontal('center');
            }
            $sheet->getStyle('A1')->getFont()->setSize(12);
            $sheet->getStyle('A2')->getFont()->setSize(11);

            // Cabeceras de columna
            $cols = ['A4'=>'Cuenta','B4'=>'Descripción Inventarios','C4'=>'Saldo Mes Anterior',
                     'D4'=>'Ingreso Procesos','E4'=>'Ingreso Caja Chica','F4'=>'Egreso Mes','G4'=>'Saldo Final Mes'];
            foreach ($cols as $cell => $val) {
                $sheet->setCellValue($cell, $val);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('4a5e3a');
                $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');
            }

            // Datos
            $row = 5;
            $totales = ['saldo_anterior'=>0,'ingreso_procesos'=>0,'ingreso_caja_chica'=>0,'egreso_mes'=>0,'saldo_final'=>0];
            foreach ($resultado as $r) {
                $sheet->setCellValue("A{$row}", $r['cuenta']);
                $sheet->setCellValue("B{$row}", $r['descripcion']);
                $sheet->setCellValue("C{$row}", $r['saldo_anterior']);
                $sheet->setCellValue("D{$row}", $r['ingreso_procesos']);
                $sheet->setCellValue("E{$row}", $r['ingreso_caja_chica']);
                $sheet->setCellValue("F{$row}", $r['egreso_mes']);
                $sheet->setCellValue("G{$row}", $r['saldo_final']);
                foreach (['C','D','E','F','G'] as $col) {
                    $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
                if ($row % 2 !== 0) {
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('f7f9f4');
                }
                foreach (['saldo_anterior','ingreso_procesos','ingreso_caja_chica','egreso_mes','saldo_final'] as $k) {
                    $totales[$k] += $r[$k];
                }
                $row++;
            }

            // Fila de totales
            $sheet->setCellValue("A{$row}", 'TOTAL');
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("C{$row}", $totales['saldo_anterior']);
            $sheet->setCellValue("D{$row}", $totales['ingreso_procesos']);
            $sheet->setCellValue("E{$row}", $totales['ingreso_caja_chica']);
            $sheet->setCellValue("F{$row}", $totales['egreso_mes']);
            $sheet->setCellValue("G{$row}", $totales['saldo_final']);
            $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:G{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('e0e8d8');
            foreach (['C','D','E','F','G'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $sheet->getColumnDimension('A')->setWidth(12);
            $sheet->getColumnDimension('B')->setWidth(50);
            foreach (['C','D','E','F','G'] as $col) {
                $sheet->getColumnDimension($col)->setWidth(20);
            }

            $writer = new Xlsx($spreadsheet);
            ob_start(); $writer->save('php://output'); $content = ob_get_clean();
            return response($content, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"inventario-mensual-{$periodo}.xlsx\"",
            ]);
        }

        return response()->json($resultado);
    }

    public function inventarioValorizado(Request $request)
    {
        $tipo            = $request->get('tipo', 'agrupado');
        $soloExistencias = $request->get('solo_existencias', '1') === '1';

        $query = DB::table('adq.articulo as a')
            ->leftJoin('adq.catalogo_nivel1 as cn1', 'cn1.nivel1', '=', 'a.nivel1')
            ->where('a.estado', 'ACTIVO')
            ->select([
                'a.id', 'a.codigo', 'a.nombre', 'a.unidad_medida',
                'a.stock_actual', 'a.precio_unitario', 'a.nivel1',
                DB::raw("COALESCE(cn1.descripcion, 'SIN CLASIFICACIÓN') as nivel1_descripcion"),
                DB::raw('ROUND(CAST(a.stock_actual AS numeric) * CAST(a.precio_unitario AS numeric), 2) as valor_total'),
            ]);

        if ($soloExistencias) {
            $query->where('a.stock_actual', '>', 0);
        }

        $query->orderByRaw("COALESCE(cn1.descripcion, 'SIN CLASIFICACIÓN')")->orderBy('a.codigo');

        $articulos    = $query->get();
        $totalGeneral = round($articulos->sum('valor_total'), 2);

        $grupos = $articulos->groupBy('nivel1_descripcion')->map(function ($items, $desc) {
            return [
                'nivel1'      => $items->first()->nivel1 ?? null,
                'descripcion' => $desc,
                'articulos'   => $items->values(),
                'subtotal'    => round($items->sum('valor_total'), 2),
            ];
        })->sortKeys()->values();

        $data = [
            'tipo'             => $tipo,
            'solo_existencias' => $soloExistencias,
            'grupos'           => $grupos,
            'articulos'        => $articulos->values(),
            'total_general'    => $totalGeneral,
        ];

        if ($request->formato === 'pdf') {
            $logoPath   = public_path('logo.png');
            $logo       = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
            $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
            $generadoPor = trim($request->user()->apellido_emp . ' ' . $request->user()->nombre_emp);
            $pdf = Pdf::loadView('reportes.adq_inventario_valorizado', array_merge($data, compact('logo', 'nombreInst', 'generadoPor')))
                ->setPaper('a4', 'landscape');
            return $pdf->stream('inventario-valorizado.pdf');
        }

        if ($request->formato === 'excel') {
            return $this->inventarioValorizadoExcel($data, $request);
        }

        return response()->json($data);
    }

    private function inventarioValorizadoExcel(array $data, Request $request)
    {
        $nombreInst = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'nombre_institucion'")->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
        $agrupado   = $data['tipo'] === 'agrupado';
        $titulo     = $data['solo_existencias'] ? 'INVENTARIO VALORIZADO — EXISTENCIAS ACTUALES' : 'INVENTARIO VALORIZADO — TODOS LOS ARTÍCULOS';
        $fecha      = now()->format('d/m/Y');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventario Valorizado');

        $colorVerde   = '4a5e3a';
        $colorVerdeLt = 'e8f0e3';
        $colorSubtot  = 'd0e0c8';

        // Encabezado
        $sheet->mergeCells('A1:F1'); $sheet->setCellValue('A1', strtoupper($nombreInst));
        $sheet->mergeCells('A2:F2'); $sheet->setCellValue('A2', $titulo);
        $sheet->mergeCells('A3:F3'); $sheet->setCellValue('A3', 'Generado: ' . $fecha);
        foreach (['A1','A2','A3'] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true);
            $sheet->getStyle($c)->getAlignment()->setHorizontal('center');
        }
        $sheet->getStyle('A1')->getFont()->setSize(12);

        // Cabeceras de columna
        $cols = ['A4'=>'Código','B4'=>'Descripción','C4'=>'Unidad','D4'=>'Stock','E4'=>'Precio Unit.','F4'=>'Valor Total'];
        foreach ($cols as $cell => $val) {
            $sheet->setCellValue($cell, $val);
            $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($colorVerde);
            $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');
        }

        $row = 5;
        $grupos = $agrupado ? $data['grupos'] : [['descripcion'=>null,'articulos'=>$data['articulos'],'subtotal'=>$data['total_general']]];

        foreach ($grupos as $grupo) {
            if ($agrupado && $grupo['descripcion']) {
                $sheet->mergeCells("A{$row}:F{$row}");
                $sheet->setCellValue("A{$row}", strtoupper($grupo['descripcion']));
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($colorVerde);
                $row++;
            }

            foreach ($grupo['articulos'] as $i => $a) {
                $sheet->setCellValue("A{$row}", $a->codigo);
                $sheet->setCellValue("B{$row}", $a->nombre);
                $sheet->setCellValue("C{$row}", $a->unidad_medida);
                $sheet->setCellValue("D{$row}", (float)$a->stock_actual);
                $sheet->setCellValue("E{$row}", (float)$a->precio_unitario);
                $sheet->setCellValue("F{$row}", (float)$a->valor_total);
                $sheet->getStyle("E{$row}:F{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                if ($i % 2 !== 0) {
                    $sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($colorVerdeLt);
                }
                $row++;
            }

            if ($agrupado) {
                $sheet->setCellValue("A{$row}", 'SUBTOTAL');
                $sheet->mergeCells("A{$row}:E{$row}");
                $sheet->setCellValue("F{$row}", (float)$grupo['subtotal']);
                $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($colorSubtot);
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }
        }

        // Total general
        $sheet->setCellValue("A{$row}", 'TOTAL GENERAL');
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("F{$row}", (float)$data['total_general']);
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($colorVerde);
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(48);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(16);

        $writer = new Xlsx($spreadsheet);
        ob_start(); $writer->save('php://output'); $content = ob_get_clean();
        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="inventario-valorizado.xlsx"',
        ]);
    }

    public function articulosBuscar(Request $request)
    {
        $q = $request->get('q', '');
        $articulos = DB::table('adq.articulo')
            ->where('estado', 'ACTIVO')
            ->where(function ($query) use ($q) {
                $query->where('codigo', 'ilike', "%{$q}%")
                      ->orWhere('nombre', 'ilike', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'codigo', 'nombre']);

        return response()->json($articulos);
    }

    public function analitica(Request $request)
    {
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $desde = $request->desde;
        $hasta = $request->hasta;

        // Top proveedores por monto (órdenes confirmadas)
        $topProveedores = DB::table('adq.orden_compra as o')
            ->join('adq.proveedor as p', 'p.id', '=', 'o.proveedor_id')
            ->where('o.estado', 'RECIBIDO')
            ->whereBetween('o.fecha_documento', [$desde, $hasta])
            ->select('p.ruc', 'p.nombre',
                DB::raw('COUNT(o.id) as facturas'),
                DB::raw('SUM(o.total) as total'))
            ->groupBy('p.id', 'p.ruc', 'p.nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Top áreas que más solicitan materiales
        $topAreas = DB::table('adq.solicitud_material as s')
            ->join('dbo.ad_departamento as d', 'd.id_depto', '=', 's.id_depto')
            ->join('adq.solicitud_material_det as sd', 'sd.solicitud_id', '=', 's.id')
            ->whereIn('s.estado', ['APROBADO', 'DESPACHADO', 'DESPACHADO PARCIAL'])
            ->whereBetween('s.fecha', [$desde, $hasta])
            ->where('d.id_depto', '!=', 999)
            ->select('d.nombre_depto',
                DB::raw('COUNT(DISTINCT s.id) as total_solicitudes'),
                DB::raw('SUM(sd.cantidad_solicitada) as total_articulos'))
            ->groupBy('d.id_depto', 'd.nombre_depto')
            ->orderByDesc('total_solicitudes')
            ->limit(10)
            ->get();

        // Top artículos más solicitados
        $topArticulos = DB::table('adq.solicitud_material_det as sd')
            ->join('adq.solicitud_material as s', 's.id', '=', 'sd.solicitud_id')
            ->join('adq.articulo as a', 'a.id', '=', 'sd.articulo_id')
            ->whereIn('s.estado', ['APROBADO', 'DESPACHADO', 'DESPACHADO PARCIAL'])
            ->whereBetween('s.fecha', [$desde, $hasta])
            ->select('a.nombre', 'a.codigo',
                DB::raw('SUM(sd.cantidad_solicitada) as total_solicitado'),
                DB::raw('COUNT(DISTINCT s.id) as total_solicitudes'))
            ->groupBy('a.id', 'a.nombre', 'a.codigo')
            ->orderByDesc('total_solicitado')
            ->limit(10)
            ->get();

        return response()->json([
            'top_proveedores' => $topProveedores,
            'top_areas'       => $topAreas,
            'top_articulos'   => $topArticulos,
        ]);
    }
}
