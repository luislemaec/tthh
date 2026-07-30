<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class LotaipController extends Controller
{
    private function empleadosBase()
    {
        return DB::table('dbo.ad_empleado as e')
            ->join('dbo.ad_departamento as d', 'e.id_depto', '=', 'd.id_depto')
            ->where('e.estado', 'ACTIVO')
            ->where('e.id_depto', '!=', 999)
            ->orderBy('e.apellido_emp')
            ->orderBy('e.nombre_emp');
    }

    private function config(string $concepto): string
    {
        $row = DB::table('dbo.d2_configuracion')
            ->whereRaw("LOWER(concepto) = ?", [strtolower($concepto)])
            ->first();
        return $row?->valor ?? '';
    }

    public function directorio(Request $request)
    {
        $direccionInstitucional = $this->config('DIRECCION_INSTITUCIONAL');
        $ciudad                 = $this->config('UBICACION_DEFAULT');
        $telefonoInstitucional  = $this->config('TELEFONO_INSTITUCIONAL');

        $empleados = $this->empleadosBase()
            ->select(
                'e.id_emp', 'e.apellido_emp', 'e.nombre_emp',
                'd.nombre_depto', 'e.extension'
            )
            ->get();

        // Cargar primer email activo de cada empleado
        $idEmps = $empleados->pluck('id_emp')->toArray();
        $emails = [];
        if (!empty($idEmps)) {
            $mailRows = DB::table('dbo.ad_empleado_mail')
                ->whereIn('id_emp', $idEmps)
                ->where('estado', 'ACTIVO')
                ->orderBy('secuencial')
                ->get()
                ->groupBy('id_emp');
            foreach ($mailRows as $idEmp => $mails) {
                $emails[$idEmp] = $mails->first()->mail ?? '';
            }
        }

        $datos = $empleados->values()->map(function ($e, $i) use ($emails, $direccionInstitucional, $ciudad, $telefonoInstitucional) {
            return [
                'nro'                    => $i + 1,
                'nombres'                => trim($e->apellido_emp) . ' ' . trim($e->nombre_emp),
                'direccion'              => $e->nombre_depto,
                'direccion_institucional'=> $direccionInstitucional,
                'ciudad'                 => $ciudad,
                'telefono'               => $telefonoInstitucional,
                'extension'              => $e->extension ?? '',
                'email'                  => $emails[$e->id_emp] ?? '',
            ];
        });

        if ($request->input('formato') === 'excel') {
            return $this->excelDirectorio($datos, $ciudad);
        }

        return response()->json($datos);
    }

    public function remuneraciones(Request $request)
    {
        $empleados = $this->empleadosBase()
            ->select(
                'e.cargo_empleado', 'e.tipo_contrato', 'e.partida_individual',
                'e.nivel', 'e.sueldo'
            )
            ->get();

        $datos = $empleados->values()->map(function ($e, $i) {
            return [
                'nro'                    => $i + 1,
                'cargo'                  => $e->cargo_empleado ?? '',
                'tipo_contrato'          => $e->tipo_contrato  ?? '',
                'partida_individual'     => $e->partida_individual ?? '',
                'grado'                  => $e->nivel ?? '',
                'salario_base'           => round((float)($e->sueldo ?? 0), 2),
                'remuneracion_anual'     => round((float)($e->sueldo ?? 0) * 12, 2),
                'decimo_tercero'         => '',
                'decimo_cuarto'          => '',
            ];
        });

        if ($request->input('formato') === 'excel') {
            return $this->excelRemuneraciones($datos);
        }

        return response()->json($datos);
    }

    // ── Excel Tab 1 ────────────────────────────────────────────────────────────

    private function excelDirectorio($datos, string $ciudad)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Directorio y Distributivo');

        $verde = '0b5447';
        $headers = [
            'Nro', 'Apellidos y Nombres', 'Dirección / Área',
            'Dirección Institucional', 'Ciudad',
            'Teléfono Institucional', 'Extensión', 'Correo Electrónico',
        ];
        $cols = ['A','B','C','D','E','F','G','H'];
        $widths = [6, 35, 30, 35, 18, 22, 12, 35];

        foreach ($cols as $k => $col) {
            $cell = $col . '1';
            $sheet->setCellValue($cell, $headers[$k]);
            $sheet->getColumnDimension($col)->setWidth($widths[$k]);
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $verde]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
            ]);
        }
        $sheet->getRowDimension(1)->setRowHeight(20);

        foreach ($datos as $i => $row) {
            $r = $i + 2;
            $bg = ($i % 2 === 0) ? 'FFF9FAFB' : 'FFFFFFFF';
            $values = [
                $row['nro'], $row['nombres'], $row['direccion'],
                $row['direccion_institucional'], $row['ciudad'],
                $row['telefono'], $row['extension'], $row['email'],
            ];
            foreach ($cols as $k => $col) {
                $sheet->setCellValue($col . $r, $values[$k]);
                $sheet->getStyle($col . $r)->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }
            $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getStyle('A1:H1')->getAlignment()->setWrapText(false);

        return $this->streamExcel($spreadsheet, 'LOTAIP_Directorio_' . date('Ymd') . '.xlsx');
    }

    // ── Excel Tab 2 ────────────────────────────────────────────────────────────

    private function excelRemuneraciones($datos)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Remuneraciones');

        $verde = '0b5447';
        $headers = [
            'Nro', 'Cargo / Denominación del Puesto', 'Tipo de Contrato',
            'Partida Individual', 'Grado', 'Salario Base',
            'Remuneración Anual Unificada', 'Décimo Tercero', 'Décimo Cuarto',
        ];
        $cols   = ['A','B','C','D','E','F','G','H','I'];
        $widths = [6, 38, 22, 18, 8, 16, 28, 16, 16];

        foreach ($cols as $k => $col) {
            $cell = $col . '1';
            $sheet->setCellValue($cell, $headers[$k]);
            $sheet->getColumnDimension($col)->setWidth($widths[$k]);
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . $verde]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
            ]);
        }
        $sheet->getRowDimension(1)->setRowHeight(20);

        foreach ($datos as $i => $row) {
            $r = $i + 2;
            $bg = ($i % 2 === 0) ? 'FFF9FAFB' : 'FFFFFFFF';
            $values = [
                $row['nro'], $row['cargo'], $row['tipo_contrato'],
                $row['partida_individual'], $row['grado'],
                $row['salario_base'], $row['remuneracion_anual'],
                $row['decimo_tercero'], $row['decimo_cuarto'],
            ];
            foreach ($cols as $k => $col) {
                $sheet->setCellValue($col . $r, $values[$k]);
                $sheet->getStyle($col . $r)->applyFromArray([
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }
            $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            // Números con 2 decimales
            foreach (['F', 'G'] as $col) {
                $sheet->getStyle($col . $r)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
        }

        return $this->streamExcel($spreadsheet, 'LOTAIP_Remuneraciones_' . date('Ymd') . '.xlsx');
    }

    private function streamExcel(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'max-age=0');
    }
}
