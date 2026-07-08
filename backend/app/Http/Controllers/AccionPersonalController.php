<?php
namespace App\Http\Controllers;

use App\Models\AccionPersonal;
use App\Models\Empleado;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AccionPersonalController extends Controller
{
    private string $alfrescoBase = 'http://192.168.26.38:8080/alfresco/api/-default-/public/alfresco/versions/1';
    private string $alfrescoUser = 'admin';
    private string $alfrescoPass = 'admin';
    private string $alfrescoSite = 'talentohumano';

    private function getDocLibNodeId(): string
    {
        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/sites/{$this->alfrescoSite}/containers/documentLibrary");
        if (!$resp->successful()) abort(502, 'No se pudo conectar con Alfresco');
        return $resp->json('entry.id');
    }

    private function getOrCreateFolderNodeId(string $parentNodeId, string $folderName): string
    {
        $buscarPorNombre = function (string $parent, string $nombre): ?string {
            $resp    = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
                ->get("{$this->alfrescoBase}/nodes/{$parent}/children", [
                    'where'    => '(isFolder=true)',
                    'maxItems' => 500,
                ]);
            $entries = $resp->json('list.entries') ?? [];
            foreach ($entries as $e) {
                if ($e['entry']['name'] === $nombre) return $e['entry']['id'];
            }
            return null;
        };

        $found = $buscarPorNombre($parentNodeId, $folderName);
        if ($found) return $found;

        $create = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->post("{$this->alfrescoBase}/nodes/{$parentNodeId}/children", [
                'name'     => $folderName,
                'nodeType' => 'cm:folder',
            ]);

        if ($create->status() === 409) {
            $found = $buscarPorNombre($parentNodeId, $folderName);
            if ($found) return $found;
        }

        if (!$create->successful()) abort(502, 'No se pudo crear la carpeta en Alfresco');
        return $create->json('entry.id');
    }

    private function queryFiltrada(Request $request)
    {
        $query = AccionPersonal::with(["empleado", "titular"])
            ->orderByDesc("created_at");

        if ($request->filled("tipo_accion")) {
            $query->where("tipo_accion", $request->tipo_accion);
        }
        if ($request->filled("estado")) {
            $query->where("estado", $request->estado);
        }
        if ($request->filled("buscar")) {
            $b = $request->buscar;
            $query->where(function ($q) use ($b) {
                $q->whereHas("empleado", function ($q2) use ($b) {
                    $q2->where("nombre_emp",       "ilike", "%$b%")
                       ->orWhere("apellido_emp",   "ilike", "%$b%")
                       ->orWhere("identificacion", "ilike", "%$b%");
                })->orWhere("numero_accion", "ilike", "%$b%");
            });
        }

        return $query;
    }

    // GET /api/acciones-personal
    public function index(Request $request)
    {
        // Auto-cerrar acciones ACTIVAS con fecha_fin vencida (SUBROGACION, VACACIONES)
        AccionPersonal::whereIn("tipo_accion", ["SUBROGACION", "VACACIONES"])
            ->where("estado", "ACTIVO")
            ->whereNotNull("fecha_fin")
            ->where("fecha_fin", "<", now()->toDateString())
            ->update(["estado" => "FINALIZADO", "updated_at" => now()]);

        $paginated = $this->queryFiltrada($request)->paginate($request->get("per_page", 15));

        // Poblar firmantes desde config para acciones BORRADOR que no los tengan
        $items = $paginated->getCollection();
        $sinFirmantes = $items->where('estado', 'BORRADOR')
            ->filter(fn($a) => !$a->firmante_th_nombre && !$a->firmante_th_cargo);

        if ($sinFirmantes->isNotEmpty()) {
            $cfg = Configuracion::whereIn("concepto", [
                "FIRMANTE_TH_NOMBRE", "FIRMANTE_TH_CARGO",
                "FIRMANTE_AUTORIDAD_NOMBRE", "FIRMANTE_AUTORIDAD_CARGO",
            ])->pluck("valor", "concepto");

            foreach ($sinFirmantes as $accion) {
                $accion->firmante_th_nombre        = $cfg['FIRMANTE_TH_NOMBRE']        ?? '';
                $accion->firmante_th_cargo         = $cfg['FIRMANTE_TH_CARGO']         ?? '';
                $accion->firmante_autoridad_nombre = $cfg['FIRMANTE_AUTORIDAD_NOMBRE'] ?? '';
                $accion->firmante_autoridad_cargo  = $cfg['FIRMANTE_AUTORIDAD_CARGO']  ?? '';
            }
            $paginated->setCollection($items);
        }

        return response()->json($paginated);
    }

    // GET /api/acciones-personal/{id}
    public function show($id)
    {
        return response()->json(AccionPersonal::with(["empleado", "titular"])->findOrFail($id));
    }

    // POST /api/acciones-personal  → crea en BORRADOR sin número
    public function store(Request $request)
    {
        $tiposSinPropuesta  = ['DESTITUCION', 'CESACION DE FUNCIONES', 'VACACIONES'];
        $tiposConFechaFin   = ['SUBROGACION', 'VACACIONES'];
        $conPropuesta       = !in_array($request->tipo_accion, $tiposSinPropuesta);
        $fechaFinRequerida  = in_array($request->tipo_accion, $tiposConFechaFin);

        $request->validate([
            "tipo_accion"            => "required|in:ENCARGO,SUBROGACION,INGRESO,VACACIONES,DESTITUCION,CESACION DE FUNCIONES",
            "fecha_elaboracion"      => "required|date",
            "id_emp"                 => "required|string",
            "fecha_inicio"           => "required|date",
            "fecha_fin"              => ($fechaFinRequerida ? "required" : "nullable") . "|date|after_or_equal:fecha_inicio",
            "motivacion"             => "nullable|string",
            "propuesto_cargo"        => ($conPropuesta ? "required" : "nullable") . "|string|max:200",
            "propuesto_grupo_ocup"   => "nullable|string|max:100",
            "propuesto_grado"        => "nullable|integer",
            "propuesto_remuneracion" => ($conPropuesta ? "required" : "nullable") . "|numeric|min:0",
            "propuesto_partida"      => "nullable|string|max:60",
            "propuesto_proceso_inst" => "nullable|string|max:30",
        ]);

        $emp          = Empleado::findOrFail($request->id_emp);
        $esIngreso    = $request->tipo_accion === 'INGRESO';
        $propuestoRem = (float) $request->propuesto_remuneracion;
        $actualRem    = $esIngreso ? 0.0 : (float) ($emp->sueldo ?? 0);
        $diferencial  = max(0, $propuestoRem - $actualRem);

        // Firmantes: usar los del form o pre-llenar desde configuración
        $cfgF = Configuracion::whereIn("concepto", [
            "FIRMANTE_TH_NOMBRE", "FIRMANTE_TH_CARGO",
            "FIRMANTE_AUTORIDAD_NOMBRE", "FIRMANTE_AUTORIDAD_CARGO",
        ])->pluck("valor", "concepto");

        $accion = AccionPersonal::create([
            "numero_accion"             => null,
            "tipo_accion"               => $request->tipo_accion,
            "fecha_elaboracion"         => $request->fecha_elaboracion,
            "id_emp"                    => $emp->id_emp,
            "id_emp_titular"            => $request->id_emp_titular ?? null,
            "fecha_inicio"              => $request->fecha_inicio,
            "fecha_fin"                 => $request->fecha_fin ?? null,
            "motivacion"                => $request->motivacion,
            "actual_cargo"              => $esIngreso ? null : $emp->cargo_empleado,
            "actual_grupo_ocup"         => $esIngreso ? null : $emp->grupo_ocupacional,
            "actual_grado"              => $esIngreso ? null : $emp->nivel,
            "actual_remuneracion"       => $actualRem,
            "actual_partida"            => $esIngreso ? null : ($emp->partida_presupuestaria
                ? ($emp->partida_presupuestaria . ($emp->partida_individual ? "-{$emp->partida_individual}" : ""))
                : null),
            "actual_proceso_inst"       => $esIngreso ? null : $emp->proceso_institucional,
            "propuesto_cargo"           => $request->propuesto_cargo,
            "propuesto_grupo_ocup"      => $request->propuesto_grupo_ocup,
            "propuesto_grado"           => $request->propuesto_grado,
            "propuesto_remuneracion"    => $propuestoRem,
            "propuesto_partida"         => $request->propuesto_partida,
            "propuesto_proceso_inst"    => $request->propuesto_proceso_inst,
            "diferencial"               => $diferencial,
            "estado"                    => "BORRADOR",
            "creado_por"                => $request->user()->id_emp,
            "firmante_th_nombre"        => strtoupper(trim($request->firmante_th_nombre       ?? $cfgF['FIRMANTE_TH_NOMBRE']        ?? '')),
            "firmante_th_cargo"         => strtoupper(trim($request->firmante_th_cargo        ?? $cfgF['FIRMANTE_TH_CARGO']         ?? '')),
            "firmante_autoridad_nombre" => strtoupper(trim($request->firmante_autoridad_nombre ?? $cfgF['FIRMANTE_AUTORIDAD_NOMBRE'] ?? '')),
            "firmante_autoridad_cargo"  => strtoupper(trim($request->firmante_autoridad_cargo  ?? $cfgF['FIRMANTE_AUTORIDAD_CARGO']  ?? '')),
        ]);

        return response()->json($accion->load(["empleado", "titular"]), 201);
    }

    // PATCH /api/acciones-personal/{id}/procesar → asigna número y pasa a ACTIVO
    public function procesar(Request $request, $id)
    {
        $accion = AccionPersonal::findOrFail($id);

        if ($accion->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se pueden procesar acciones en estado BORRADOR.'], 422);
        }

        $prefijo = Configuracion::where("concepto", "PREFIJO_ACCION_PERSONAL")->value("valor") ?? "DATH";
        $anio    = Carbon::now()->year;
        $ultimo  = AccionPersonal::whereYear("created_at", $anio)
            ->whereNotNull("numero_accion")
            ->max(DB::raw("CAST(SPLIT_PART(numero_accion, '-', 3) AS INTEGER)")) ?? 0;
        $numero       = str_pad($ultimo + 1, 5, "0", STR_PAD_LEFT);
        $numeroAccion = "{$prefijo}-{$anio}-{$numero}";

        $accion->update([
            'numero_accion' => $numeroAccion,
            'estado'        => 'ACTIVO',
            'updated_at'    => now(),
        ]);

        return response()->json([
            'message' => "Acción procesada con número {$numeroAccion}.",
            'accion'  => $accion->load(['empleado', 'titular']),
        ]);
    }

    // PATCH /api/acciones-personal/{id}/editar-borrador → edita motivación, fecha elaboración y firmantes
    public function editarBorrador(Request $request, $id)
    {
        $request->validate([
            'motivacion'               => 'nullable|string',
            'fecha_elaboracion'        => 'required|date',
            'firmante_th_nombre'       => 'nullable|string|max:200',
            'firmante_th_cargo'        => 'nullable|string|max:200',
            'firmante_autoridad_nombre' => 'nullable|string|max:200',
            'firmante_autoridad_cargo' => 'nullable|string|max:200',
        ]);

        $accion = AccionPersonal::findOrFail($id);

        if ($accion->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se pueden editar acciones en estado BORRADOR.'], 422);
        }

        $accion->update([
            'motivacion'               => $request->motivacion,
            'fecha_elaboracion'        => $request->fecha_elaboracion,
            'firmante_th_nombre'       => strtoupper(trim($request->firmante_th_nombre       ?? '')),
            'firmante_th_cargo'        => strtoupper(trim($request->firmante_th_cargo        ?? '')),
            'firmante_autoridad_nombre' => strtoupper(trim($request->firmante_autoridad_nombre ?? '')),
            'firmante_autoridad_cargo' => strtoupper(trim($request->firmante_autoridad_cargo  ?? '')),
            'updated_at'               => now(),
        ]);

        return response()->json(['message' => 'Acción actualizada.', 'accion' => $accion]);
    }

    // PATCH /api/acciones-personal/{id}/estado
    public function cambiarEstado(Request $request, $id)
    {
        $request->validate([
            "estado"    => "required|in:ACTIVO,FINALIZADO,ANULADO",
            "fecha_fin" => "nullable|date",
        ]);
        $accion = AccionPersonal::findOrFail($id);

        $data = ["estado" => $request->estado, "updated_at" => now()];
        if ($request->estado === "FINALIZADO" && $request->filled("fecha_fin")) {
            $data["fecha_fin"] = $request->fecha_fin;
        }

        $accion->update($data);
        return response()->json(["message" => "Estado actualizado correctamente."]);
    }

    // GET /api/acciones-personal/{id}/pdf  (funciona en BORRADOR y procesadas)
    public function pdf($id)
    {
        $accion = AccionPersonal::with(["empleado.departamento", "titular.departamento"])->findOrFail($id);

        $creador = Empleado::find($accion->creado_por);
        $config  = Configuracion::whereIn("concepto", [
            "DIRECTOR_TALENTO_HUMANO",
            "APROBADOR_ACCION_PERSONAL",
            "nombre_institucion",
            "PREFIJO_ACCION_PERSONAL",
            "FIRMANTE_TH_NOMBRE",
            "FIRMANTE_TH_CARGO",
            "FIRMANTE_AUTORIDAD_NOMBRE",
            "FIRMANTE_AUTORIDAD_CARGO",
        ])->pluck("valor", "concepto");

        // Firmantes: primero desde la acción, fallback a configuración global
        $firmanteThNombre    = $accion->firmante_th_nombre        ?: ($config['FIRMANTE_TH_NOMBRE']        ?? $config['DIRECTOR_TALENTO_HUMANO']   ?? '');
        $firmanteThCargo     = $accion->firmante_th_cargo         ?: ($config['FIRMANTE_TH_CARGO']         ?? 'DIRECTOR (A) DE ADMINISTRACIÓN DEL TALENTO HUMANO');
        $firmanteAutNombre   = $accion->firmante_autoridad_nombre ?: ($config['FIRMANTE_AUTORIDAD_NOMBRE'] ?? $config['APROBADOR_ACCION_PERSONAL']  ?? '');
        $firmanteAutCargo    = $accion->firmante_autoridad_cargo  ?: ($config['FIRMANTE_AUTORIDAD_CARGO']  ?? '');

        $logoPath   = public_path("logo.png");
        $logoBase64 = file_exists($logoPath)
            ? "data:image/png;base64," . base64_encode(file_get_contents($logoPath))
            : null;

        $nombreArchivo = $accion->numero_accion
            ? "accion_personal_{$accion->numero_accion}.pdf"
            : "accion_personal_borrador.pdf";

        $pdfInstance = Pdf::loadView("reportes.accion_personal", [
            "accion"              => $accion,
            "config"              => $config,
            "logo"                => $logoBase64,
            "creador"             => $creador,
            "firmanteThNombre"    => $firmanteThNombre,
            "firmanteThCargo"     => $firmanteThCargo,
            "firmanteAutNombre"   => $firmanteAutNombre,
            "firmanteAutCargo"    => $firmanteAutCargo,
        ])->setPaper("a4", "portrait");

        return $pdfInstance->stream($nombreArchivo);
    }

    // GET /api/acciones-personal/reporte/pdf
    public function reportePdf(Request $request)
    {
        $acciones = $this->queryFiltrada($request)->get();

        $config = Configuracion::whereIn("concepto", [
            "nombre_institucion",
            "PREFIJO_ACCION_PERSONAL",
        ])->pluck("valor", "concepto");

        $logoPath   = public_path("logo.png");
        $logoBase64 = file_exists($logoPath)
            ? "data:image/png;base64," . base64_encode(file_get_contents($logoPath))
            : null;

        $filtros = array_filter([
            $request->filled("tipo_accion") ? "Tipo: " . $request->tipo_accion : null,
            $request->filled("estado")      ? "Estado: " . $request->estado    : null,
            $request->filled("buscar")      ? "Búsqueda: " . $request->buscar  : null,
        ]);

        $pdf = Pdf::loadView("reportes.acc_lista", [
            "acciones" => $acciones,
            "config"   => $config,
            "logo"     => $logoBase64,
            "filtros"  => implode(" | ", $filtros) ?: "Todos",
            "fecha"    => now()->format("d/m/Y H:i"),
            "generadoPor" => trim($request->user()->apellido_emp . " " . $request->user()->nombre_emp),
        ])->setPaper("a4", "landscape");

        return $pdf->download("acciones_personal_" . now()->format("Ymd") . ".pdf");
    }

    // GET /api/acciones-personal/reporte/excel
    public function reporteExcel(Request $request)
    {
        $acciones = $this->queryFiltrada($request)->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Acciones de Personal");

        // Encabezados
        $headers = ['Nro. Acción', 'Tipo', 'Empleado', 'Identificación', 'Cargo', 'Fecha Elaboración', 'Fecha Inicio', 'Fecha Fin', 'Estado', 'Motivación'];
        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('1a4731');
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Datos
        foreach ($acciones as $i => $a) {
            $row = $i + 2;
            $emp = $a->empleado;
            $sheet->setCellValue("A{$row}", $a->numero_accion ?? 'BORRADOR');
            $sheet->setCellValue("B{$row}", $a->tipo_accion);
            $sheet->setCellValue("C{$row}", $emp ? trim("{$emp->apellido_emp} {$emp->nombre_emp}") : '');
            $sheet->setCellValue("D{$row}", $emp?->identificacion ?? '');
            $sheet->setCellValue("E{$row}", $emp?->cargo_empleado ?? '');
            $sheet->setCellValue("F{$row}", $a->fecha_elaboracion ? substr($a->fecha_elaboracion, 0, 10) : '');
            $sheet->setCellValue("G{$row}", $a->fecha_inicio     ? substr($a->fecha_inicio, 0, 10)     : '');
            $sheet->setCellValue("H{$row}", $a->fecha_fin        ? substr($a->fecha_fin, 0, 10)        : '');
            $sheet->setCellValue("I{$row}", $a->estado);
            $sheet->setCellValue("J{$row}", $a->motivacion ?? '');

            if ($i % 2 === 0) {
                $sheet->getStyle("A{$row}:J{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('f0fdf4');
            }
        }

        // Autosize columnas A-I (J puede ser larga)
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('J')->setWidth(50);
        $sheet->getStyle('J2:J' . max(2, $acciones->count() + 1))->getAlignment()->setWrapText(true);

        $writer   = new Xlsx($spreadsheet);
        $filename = "acciones_personal_" . now()->format("Ymd") . ".xlsx";

        return response()->stream(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    // POST /api/acciones-personal/{id}/subir-firmado
    public function subirFirmado(Request $request, $id)
    {
        $request->validate(["archivo" => "required|file|mimes:pdf|max:20480"]);
        $accion = AccionPersonal::findOrFail($id);

        if ($accion->estado === 'BORRADOR') {
            return response()->json(['message' => 'Procese la acción antes de subir el documento firmado.'], 422);
        }

        if ($accion->pdf_firmado) {
            Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
                ->delete("{$this->alfrescoBase}/nodes/{$accion->pdf_firmado}");
        }

        $anio         = Carbon::parse($accion->fecha_elaboracion)->year;
        $docLibId     = $this->getDocLibNodeId();
        $archivo      = $request->file("archivo");
        $nombre       = "accion_{$accion->numero_accion}_firmado.pdf";
        $relativePath = "acciones-personal/{$anio}";

        $upload = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach("filedata", file_get_contents($archivo->getRealPath()), $nombre)
            ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
                "name"         => $nombre,
                "nodeType"     => "cm:content",
                "relativePath" => $relativePath,
                "autoRename"   => true,
            ]);

        if (!$upload->successful()) {
            return response()->json(["message" => "Error al subir el archivo a Alfresco"], 502);
        }

        $accion->update(["pdf_firmado" => $upload->json("entry.id")]);
        return response()->json(["message" => "PDF firmado subido correctamente."]);
    }

    // GET /api/acciones-personal/{id}/descargar-firmado
    public function descargarFirmado($id)
    {
        $accion = AccionPersonal::findOrFail($id);
        if (!$accion->pdf_firmado) {
            return response()->json(["message" => "No hay PDF firmado disponible."], 404);
        }

        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/nodes/{$accion->pdf_firmado}/content");

        if (!$resp->successful()) {
            return response()->json(["message" => "No se pudo obtener el archivo desde Alfresco"], 502);
        }

        return response($resp->body(), 200, [
            "Content-Type"        => "application/pdf",
            "Content-Disposition" => "attachment; filename=\"accion_firmada_{$accion->numero_accion}.pdf\"",
        ]);
    }
}
