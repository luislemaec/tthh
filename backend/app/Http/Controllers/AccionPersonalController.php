<?php
namespace App\Http\Controllers;

use App\Models\AccionPersonal;
use App\Models\Empleado;
use App\Models\Configuracion;
use App\Services\AuditoriaService;
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
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO', 'TH ACCIONES PERSONAL'];

    private string $alfrescoBase;
    private string $alfrescoUser;
    private string $alfrescoPass;
    private string $alfrescoSite;

    public function __construct()
    {
        $this->alfrescoBase = config('services.alfresco.base');
        $this->alfrescoUser = config('services.alfresco.user');
        $this->alfrescoPass = config('services.alfresco.pass');
        $this->alfrescoSite = config('services.alfresco.site');
    }

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

    // GET /api/acciones-personal/ultima-activa/{id_emp}
    public function ultimaActiva(Request $request, $id_emp)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        // Busca la última acción ACTIVO del empleado (excluye VACACIONES, ENCARGO, SUBROGACION
        // porque no cambian la posición permanente del servidor)
        $accion = DB::table('dbo.acc_accion_personal')
            ->where('id_emp', $id_emp)
            ->where('estado', 'ACTIVO')
            ->whereNotIn('tipo_accion', ['VACACIONES', 'ENCARGO', 'SUBROGACION'])
            ->orderByDesc('fecha_elaboracion')
            ->orderByDesc('id')
            ->first();

        if (!$accion) return response()->json(null);

        $sinActual = in_array($accion->tipo_accion, ['INGRESO', 'REINGRESO']);

        return response()->json([
            'tipo_accion'         => $accion->tipo_accion,
            'actual_cargo'        => $sinActual ? $accion->propuesto_cargo       : $accion->actual_cargo,
            'actual_grupo_ocup'   => $sinActual ? $accion->propuesto_grupo_ocup  : $accion->actual_grupo_ocup,
            'actual_grado'        => $sinActual ? $accion->propuesto_grado       : $accion->actual_grado,
            'actual_remuneracion' => $sinActual ? $accion->propuesto_remuneracion: $accion->actual_remuneracion,
            'actual_partida'      => $sinActual ? $accion->propuesto_partida     : $accion->actual_partida,
            'actual_proceso_inst' => $sinActual ? $accion->propuesto_proceso_inst: $accion->actual_proceso_inst,
        ]);
    }

    // GET /api/acciones-personal
    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        // El auto-cierre de acciones vencidas ya NO vive acá — ver comando
        // cerrar:acciones-vencidas (antes era efecto secundario de este GET).

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
    public function show(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        return response()->json(AccionPersonal::with(["empleado", "titular"])->findOrFail($id));
    }

    // POST /api/acciones-personal  → crea en BORRADOR sin número
    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $tiposSinPropuesta  = ['DESTITUCION', 'CESACION DE FUNCIONES', 'VACACIONES', 'COMISION DE SERVICIOS'];
        $tiposSinActual     = ['INGRESO', 'REINGRESO'];
        $tiposConFechaFin   = ['SUBROGACION', 'VACACIONES', 'COMISION DE SERVICIOS'];
        $conPropuesta       = !in_array($request->tipo_accion, $tiposSinPropuesta);
        $fechaFinRequerida  = in_array($request->tipo_accion, $tiposConFechaFin);

        $request->validate([
            "tipo_accion"            => "required|in:ENCARGO,SUBROGACION,INGRESO,VACACIONES,DESTITUCION,CESACION DE FUNCIONES,COMISION DE SERVICIOS,REINGRESO",
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

        $emp = Empleado::findOrFail($request->id_emp);

        // Estado del empleado contra el tipo de acción (ver tabla en CLAUDE.md) — antes
        // solo lo filtraba el buscador del frontend (ACTIVO vs INACTIVO), la API no lo
        // exigía: se podía crear una DESTITUCION sobre un empleado ACTIVO, o un INGRESO
        // sobre uno INACTIVO, llamando directo al endpoint.
        $estadoRequerido = $request->tipo_accion === 'DESTITUCION' ? 'INACTIVO' : 'ACTIVO';
        if (strtoupper($emp->estado) !== $estadoRequerido) {
            return response()->json([
                'message' => "Para {$request->tipo_accion} el empleado debe estar {$estadoRequerido} (actualmente: {$emp->estado})."
            ], 422);
        }

        $sinActual    = in_array($request->tipo_accion, ['INGRESO', 'REINGRESO']);
        $propuestoRem = (float) $request->propuesto_remuneracion;
        $actualRem    = $sinActual ? 0.0 : (float) ($request->actual_remuneracion ?? $emp->sueldo ?? 0);
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
            "actual_cargo"              => $sinActual ? null : ($request->actual_cargo       ?? $emp->cargo_empleado),
            "actual_grupo_ocup"         => $sinActual ? null : ($request->actual_grupo_ocup  ?? $emp->grupo_ocupacional),
            "actual_grado"              => $sinActual ? null : ($request->actual_grado        ?? $emp->nivel),
            "actual_remuneracion"       => $sinActual ? 0.0  : (float) ($request->actual_remuneracion ?? $emp->sueldo ?? 0),
            "actual_partida"            => $sinActual ? null : ($request->actual_partida      ?? ($emp->partida_presupuestaria
                ? ($emp->partida_presupuestaria . ($emp->partida_individual ? "-{$emp->partida_individual}" : ""))
                : null)),
            "actual_proceso_inst"       => $sinActual ? null : ($request->actual_proceso_inst ?? $emp->proceso_institucional),
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
            "medio"                     => in_array($request->medio, ['DIGITAL', 'MANUAL']) ? $request->medio : 'DIGITAL',
            "especificacion"            => $request->especificacion ? strtoupper(trim($request->especificacion)) : null,
        ]);

        AuditoriaService::log('dbo.acc_accion_personal', $accion->getKey(), 'CREAR',
            null,
            ['tipo_accion' => $accion->tipo_accion, 'id_emp' => $accion->id_emp, 'estado' => $accion->estado],
            $request, "Creación de acción de personal ({$accion->tipo_accion}) en BORRADOR: " . trim($emp->apellido_emp . ' ' . $emp->nombre_emp));

        return response()->json($accion->load(["empleado", "titular"]), 201);
    }

    // PATCH /api/acciones-personal/{id}/procesar → asigna número y pasa a ACTIVO
    public function procesar(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $accion = AccionPersonal::findOrFail($id);

        if ($accion->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se pueden procesar acciones en estado BORRADOR.'], 422);
        }

        $prefijo = Configuracion::where("concepto", "PREFIJO_ACCION_PERSONAL")->value("valor") ?? "DATH";
        $anio    = Carbon::now()->year;

        // Generación de número + actualización envueltas en transacción: el advisory
        // lock de AccionPersonal::generarSiguienteNumero() solo sirve mientras dure la
        // transacción que lo pidió (evita dos "procesar()" concurrentes calculando el
        // mismo número de documento oficial).
        $numeroAccion = DB::transaction(function () use ($accion, $prefijo, $anio) {
            $numeroAccion = AccionPersonal::generarSiguienteNumero($prefijo, $anio);
            $accion->update([
                'numero_accion' => $numeroAccion,
                'estado'        => 'ACTIVO',
                'updated_at'    => now(),
            ]);
            return $numeroAccion;
        });

        AuditoriaService::log('dbo.acc_accion_personal', $accion->getKey(), 'PROCESAR',
            ['estado' => 'BORRADOR', 'numero_accion' => null],
            ['estado' => 'ACTIVO', 'numero_accion' => $numeroAccion],
            $request, "Procesamiento de acción de personal: {$numeroAccion}");

        return response()->json([
            'message' => "Acción procesada con número {$numeroAccion}.",
            'accion'  => $accion->load(['empleado', 'titular']),
        ]);
    }

    // PATCH /api/acciones-personal/{id}/editar-borrador → edita motivación, fecha elaboración y firmantes
    public function editarBorrador(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            'motivacion'               => 'nullable|string',
            'fecha_elaboracion'        => 'required|date',
            'firmante_th_nombre'       => 'nullable|string|max:200',
            'firmante_th_cargo'        => 'nullable|string|max:200',
            'firmante_autoridad_nombre' => 'nullable|string|max:200',
            'firmante_autoridad_cargo' => 'nullable|string|max:200',
            'medio'                    => 'nullable|in:DIGITAL,MANUAL',
            'especificacion'           => 'nullable|string|max:300',
        ]);

        $accion = AccionPersonal::findOrFail($id);

        if ($accion->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se pueden editar acciones en estado BORRADOR.'], 422);
        }

        $anterior = ['motivacion' => $accion->motivacion, 'fecha_elaboracion' => (string) $accion->fecha_elaboracion];

        $accion->update([
            'motivacion'               => $request->motivacion,
            'fecha_elaboracion'        => $request->fecha_elaboracion,
            'firmante_th_nombre'       => strtoupper(trim($request->firmante_th_nombre       ?? '')),
            'firmante_th_cargo'        => strtoupper(trim($request->firmante_th_cargo        ?? '')),
            'firmante_autoridad_nombre' => strtoupper(trim($request->firmante_autoridad_nombre ?? '')),
            'firmante_autoridad_cargo' => strtoupper(trim($request->firmante_autoridad_cargo  ?? '')),
            'medio'                    => in_array($request->medio, ['DIGITAL', 'MANUAL']) ? $request->medio : $accion->medio,
            'especificacion'           => $request->has('especificacion') ? ($request->especificacion ? strtoupper(trim($request->especificacion)) : null) : $accion->especificacion,
            'updated_at'               => now(),
        ]);

        AuditoriaService::log('dbo.acc_accion_personal', $accion->getKey(), 'EDITAR_BORRADOR',
            $anterior,
            ['motivacion' => $accion->motivacion, 'fecha_elaboracion' => (string) $accion->fecha_elaboracion],
            $request, "Edición de borrador de acción de personal (id {$accion->getKey()})");

        return response()->json(['message' => 'Acción actualizada.', 'accion' => $accion]);
    }

    // PATCH /api/acciones-personal/{id}/estado
    public function cambiarEstado(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            "estado"    => "required|in:ACTIVO,FINALIZADO,ANULADO",
            "fecha_fin" => "nullable|date",
        ]);
        $accion = AccionPersonal::findOrFail($id);

        // Única transición válida: ACTIVO → FINALIZADO/ANULADO. BORRADOR debe pasar por
        // procesar() primero (antes se podía saltar directo a ANULADO sin número de
        // documento asignado); FINALIZADO/ANULADO quedan terminales.
        if ($accion->estado !== 'ACTIVO') {
            return response()->json([
                'message' => "No se puede cambiar el estado de una acción en {$accion->estado} (solo se permite desde ACTIVO)."
            ], 422);
        }
        if (!in_array($request->estado, ['FINALIZADO', 'ANULADO'], true)) {
            return response()->json(['message' => 'Desde ACTIVO solo se puede pasar a FINALIZADO o ANULADO.'], 422);
        }

        $anterior = ['estado' => $accion->estado];

        $data = ["estado" => $request->estado, "updated_at" => now()];
        if ($request->estado === "FINALIZADO" && $request->filled("fecha_fin")) {
            $data["fecha_fin"] = $request->fecha_fin;
        }

        $accion->update($data);

        AuditoriaService::log('dbo.acc_accion_personal', $accion->getKey(), 'CAMBIAR_ESTADO',
            $anterior,
            ['estado' => $request->estado],
            $request, "Cambio de estado de acción de personal ({$accion->numero_accion}): {$anterior['estado']} → {$request->estado}");

        return response()->json(["message" => "Estado actualizado correctamente."]);
    }

    // GET /api/acciones-personal/{id}/pdf  (funciona en BORRADOR y procesadas)
    public function pdf(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
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
            "UBICACION_DEFAULT",
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
        $this->requireRole($request, self::ROLES_ADMIN);
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
        $this->requireRole($request, self::ROLES_ADMIN);
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

    // GET /api/acciones-personal/historial-remuneraciones
    public function historialRemuneraciones(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $tiposPermitidos = ['INGRESO', 'ENCARGO', 'SUBROGACION', 'CESACION DE FUNCIONES', 'DESTITUCION'];

        $query = AccionPersonal::with(['empleado'])
            ->whereIn('tipo_accion', $tiposPermitidos)
            ->whereNotIn('estado', ['BORRADOR'])
            ->orderBy('fecha_inicio', 'asc');

        if ($request->filled('id_emp')) {
            $query->where('id_emp', $request->id_emp);
        } elseif ($request->filled('buscar')) {
            $b = $request->buscar;
            $query->whereHas('empleado', function ($q) use ($b) {
                $q->where('nombre_emp',    'ilike', "%$b%")
                  ->orWhere('apellido_emp', 'ilike', "%$b%")
                  ->orWhere('identificacion', 'ilike', "%$b%");
            });
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha_inicio', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_inicio', '<=', $request->fecha_hasta);
        }

        if ($request->filled('tipos')) {
            $tipos = is_array($request->tipos) ? $request->tipos : explode(',', $request->tipos);
            $query->whereIn('tipo_accion', array_intersect($tipos, $tiposPermitidos));
        }

        $acciones = $query->get();

        // Sueldo actual de cada empleado involucrado
        $sueldos = Empleado::whereIn('id_emp', $acciones->pluck('id_emp')->unique())
            ->pluck('sueldo', 'id_emp');

        // Tipos que usan la situación propuesta (lo que ganó en ese rol)
        $conPropuesta = ['INGRESO', 'ENCARGO', 'SUBROGACION'];

        $resultado = $acciones->map(function ($a) use ($sueldos, $conPropuesta) {
            $usaPropuesta = in_array($a->tipo_accion, $conPropuesta);
            $cargo        = $usaPropuesta ? $a->propuesto_cargo       : $a->actual_cargo;
            $remuneracion = $usaPropuesta ? $a->propuesto_remuneracion : $a->actual_remuneracion;
            $sueldoActual = (float) ($sueldos->get($a->id_emp) ?? 0);
            $diferencia   = round($sueldoActual - (float) ($remuneracion ?? 0), 2);

            return [
                'id_accion'       => $a->id_accion,
                'numero_accion'   => $a->numero_accion,
                'tipo_accion'     => $a->tipo_accion,
                'fecha_inicio'    => substr((string) $a->fecha_inicio, 0, 10),
                'fecha_fin'       => $a->fecha_fin ? substr((string) $a->fecha_fin, 0, 10) : null,
                'cargo'           => $cargo,
                'remuneracion'    => $remuneracion !== null ? (float) $remuneracion : null,
                'sueldo_actual'   => $sueldoActual,
                'diferencia'      => $diferencia,
                'empleado'        => $a->empleado ? [
                    'id_emp'          => $a->empleado->id_emp,
                    'nombre_completo' => trim($a->empleado->apellido_emp . ' ' . $a->empleado->nombre_emp),
                    'identificacion'  => $a->empleado->identificacion,
                    'estado'          => $a->empleado->estado,
                ] : null,
            ];
        })->values();

        $formato = $request->query('formato');
        if ($formato === 'pdf')   return $this->historialPdf($request, $resultado);
        if ($formato === 'excel') return $this->historialExcel($resultado);

        return response()->json($resultado);
    }

    private function historialPdf(Request $request, $filas)
    {
        $logoPath = public_path('logo.png');
        $logo     = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $nombreInst  = Configuracion::where('concepto', 'nombre_institucion')->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
        $generadoPor = trim($request->user()->apellido_emp . ' ' . $request->user()->nombre_emp);

        $filtroEmp    = $request->filled('buscar') ? $request->buscar : null;
        $filtroDates  = array_filter([$request->fecha_desde, $request->fecha_hasta]);
        $periodoLabel = count($filtroDates) === 2
            ? Carbon::parse($request->fecha_desde)->format('d/m/Y') . ' — ' . Carbon::parse($request->fecha_hasta)->format('d/m/Y')
            : null;

        $pdf = Pdf::loadView('reportes.acc_historial_remuneraciones', compact(
            'filas', 'logo', 'nombreInst', 'generadoPor', 'filtroEmp', 'periodoLabel'
        ))->setPaper('a4', 'landscape');

        return $pdf->stream('historial_remuneraciones_' . now()->format('Ymd') . '.pdf');
    }

    private function historialExcel($filas)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Historial Remuneraciones');

        $headers = ['N° Acción', 'Empleado', 'Tipo', 'Fecha Inicio', 'Fecha Fin', 'Cargo', 'Remuneración acción', 'Sueldo actual', 'Diferencia'];
        foreach ($headers as $col => $h) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $h);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1a4731');
            $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach ($filas as $i => $f) {
            $row = $i + 2;
            $dif = $f['diferencia'];
            $sheet->setCellValue("A{$row}", $f['numero_accion'] ?? '—');
            $sheet->setCellValue("B{$row}", $f['empleado']['nombre_completo'] ?? '');
            $sheet->setCellValue("C{$row}", $f['tipo_accion']);
            $sheet->setCellValue("D{$row}", $f['fecha_inicio'] ?? '');
            $sheet->setCellValue("E{$row}", $f['fecha_fin'] ?? 'Vigente');
            $sheet->setCellValue("F{$row}", $f['cargo'] ?? '');
            $sheet->setCellValue("G{$row}", $f['remuneracion'] !== null ? number_format($f['remuneracion'], 2) : '');
            $sheet->setCellValue("H{$row}", number_format($f['sueldo_actual'], 2));
            $sheet->setCellValue("I{$row}", ($dif >= 0 ? '+' : '') . number_format($dif, 2));

            if ($dif > 0) {
                $sheet->getStyle("I{$row}")->getFont()->getColor()->setRGB('166534');
            } elseif ($dif < 0) {
                $sheet->getStyle("I{$row}")->getFont()->getColor()->setRGB('991b1b');
            }

            if ($i % 2 === 0) {
                $sheet->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('f0fdf4');
            }
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('F')->setWidth(40);
        $sheet->getColumnDimension('I')->setAutoSize(true);

        $writer   = new Xlsx($spreadsheet);
        $filename = 'historial_remuneraciones_' . now()->format('Ymd') . '.xlsx';

        return response()->stream(
            fn() => $writer->save('php://output'),
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    // POST /api/acciones-personal/{id}/subir-firmado
    public function subirFirmado(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
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

        AuditoriaService::log('dbo.acc_accion_personal', $accion->getKey(), 'SUBIR_FIRMADO',
            null,
            ['numero_accion' => $accion->numero_accion, 'archivo' => $nombre],
            $request, "PDF firmado subido para acción de personal: {$accion->numero_accion}");

        return response()->json(["message" => "PDF firmado subido correctamente."]);
    }

    // GET /api/acciones-personal/{id}/descargar-firmado
    public function descargarFirmado(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
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
