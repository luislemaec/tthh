<?php
namespace App\Http\Controllers\Tecnologia;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\Tecnologia\Equipo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReporteEquipoController extends Controller
{
    private const ROLES_TEC = ['ADMINISTRADOR', 'TECNOLOGIA'];

    private const VIDA_UTIL_VENCIDA_RAW = "fecha_ingreso IS NOT NULL AND vida_util_anios IS NOT NULL
        AND (fecha_ingreso + (vida_util_anios || ' years')::interval) <= CURRENT_DATE";

    private function construirQuery(Request $request)
    {
        $query = Equipo::with(['tipoEquipo', 'asignacionActiva.empleado'])
            ->withCount('piezasInstaladas')
            ->orderBy('codigo_bien');

        if ($request->filled('id_emp')) {
            $query->whereHas('asignacionActiva', fn ($q) => $q->where('id_emp', $request->id_emp));
        }
        if ($request->filled('marca')) {
            $query->where('marca', $request->marca);
        }
        if ($request->filled('modelo')) {
            $query->where('modelo', $request->modelo);
        }
        if ($request->filled('tipo_equipo_id')) {
            $query->where('tipo_equipo_id', $request->tipo_equipo_id);
        }
        if ($request->filled('vida_util')) {
            if ($request->vida_util === 'vencida') {
                $query->whereRaw(self::VIDA_UTIL_VENCIDA_RAW);
            } elseif ($request->vida_util === 'vigente') {
                $query->whereRaw('NOT (' . self::VIDA_UTIL_VENCIDA_RAW . ')');
            }
        }

        return $query;
    }

    public function filtros(Request $request)
    {
        $this->requireRole($request, self::ROLES_TEC);
        return response()->json([
            'marcas'  => Equipo::whereNotNull('marca')->where('marca', '!=', '')->distinct()->orderBy('marca')->pluck('marca'),
            'modelos' => Equipo::whereNotNull('modelo')->where('modelo', '!=', '')->distinct()->orderBy('modelo')->pluck('modelo'),
        ]);
    }

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $equipos = $this->construirQuery($request)->get();

        if ($request->formato === 'excel') {
            return $this->exportarExcel($equipos);
        }
        if ($request->formato === 'pdf') {
            return $this->exportarPdf($equipos, $request);
        }

        return response()->json($equipos);
    }

    private function filasParaExport($equipos): array
    {
        return $equipos->map(function ($e) {
            $custodio = $e->asignacionActiva?->empleado;
            return [
                'codigo_bien'     => $e->codigo_bien,
                'tipo'            => $e->tipoEquipo->nombre ?? '',
                'marca'           => $e->marca,
                'modelo'          => $e->modelo,
                'descripcion'     => $e->descripcion,
                'serie'           => $e->serie,
                'condicion'       => $e->condicion,
                'estado'          => $e->estado,
                'fecha_ingreso'   => $e->fecha_ingreso ? (string) $e->fecha_ingreso : '',
                'vida_util_anios' => $e->vida_util_anios,
                'vida_util'       => $e->vida_util_vencida ? 'VENCIDA' : 'VIGENTE',
                'custodio'        => $custodio ? trim($custodio->apellido_emp . ' ' . $custodio->nombre_emp) : '',
                'piezas'          => $e->piezas_instaladas_count,
            ];
        })->all();
    }

    private function exportarExcel($equipos)
    {
        $filas = $this->filasParaExport($equipos);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Equipos');

        $headers = ['Código', 'Tipo', 'Marca', 'Modelo', 'Descripción', 'Serie', 'Condición', 'Estado',
            'Fecha Ingreso', 'Vida Útil (años)', 'Vida Útil', 'Custodio', 'N° Piezas Instaladas'];

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }
        $lastCol = chr(ord('A') + count($headers) - 1);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4d7c8a']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $row = 2;
        foreach ($filas as $f) {
            $c = 'A';
            foreach ($f as $val) {
                $sheet->setCellValue($c . $row, $val);
                $c++;
            }
            $row++;
        }

        for ($c = 'A'; $c <= $lastCol; $c++) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="reporte_equipos_' . now()->format('Ymd_His') . '.xlsx"',
        ]);
    }

    private function exportarPdf($equipos, Request $request)
    {
        $filas       = $this->filasParaExport($equipos);
        $nombreInst  = Configuracion::where('concepto', 'nombre_institucion')->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
        $generadoPor = trim($request->user()->apellido_emp) . ' ' . trim($request->user()->nombre_emp);
        $path        = public_path('logo.png');
        $logo        = file_exists($path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($path)) : null;

        $pdf = Pdf::loadView('reportes.ti_reporte_equipos', compact('filas', 'nombreInst', 'generadoPor', 'logo'))
            ->setPaper('a4', 'landscape');

        return $pdf->stream('reporte_equipos_' . now()->format('Ymd_His') . '.pdf');
    }
}
