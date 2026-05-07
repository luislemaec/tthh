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
        $search = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/nodes/{$parentNodeId}/children", [
                'where' => "(isFolder=true AND name='{$folderName}')",
            ]);
        $entries = $search->json('list.entries') ?? [];
        if (!empty($entries)) return $entries[0]['entry']['id'];

        $create = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->post("{$this->alfrescoBase}/nodes/{$parentNodeId}/children", [
                'name'     => $folderName,
                'nodeType' => 'cm:folder',
            ]);
        if (!$create->successful()) abort(502, 'No se pudo crear la carpeta en Alfresco');
        return $create->json('entry.id');
    }

    // GET /api/acciones-personal
    public function index(Request $request)
    {
        // Auto-cerrar acciones con fecha_fin que ya pasó (SUBROGACION, VACACIONES)
        AccionPersonal::whereIn("tipo_accion", ["SUBROGACION", "VACACIONES"])
            ->where("estado", "ACTIVO")
            ->whereNotNull("fecha_fin")
            ->where("fecha_fin", "<", now()->toDateString())
            ->update(["estado" => "FINALIZADO", "updated_at" => now()]);

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
            $query->whereHas("empleado", function ($q) use ($b) {
                $q->where("nombre_emp",      "ilike", "%$b%")
                  ->orWhere("apellido_emp",  "ilike", "%$b%")
                  ->orWhere("identificacion","ilike", "%$b%");
            })->orWhere("numero_accion", "ilike", "%$b%");
        }

        return response()->json($query->paginate($request->get("per_page", 15)));
    }

    // GET /api/acciones-personal/{id}
    public function show($id)
    {
        $accion = AccionPersonal::with(["empleado", "titular"])->findOrFail($id);
        return response()->json($accion);
    }

    // POST /api/acciones-personal
    public function store(Request $request)
    {
        $tiposSinPropuesta  = ['DESTITUCION', 'CESACION DE FUNCIONES', 'VACACIONES'];
        $tiposConFechaFin   = ['SUBROGACION', 'VACACIONES'];
        $conPropuesta       = !in_array($request->tipo_accion, $tiposSinPropuesta);
        $fechaFinRequerida  = in_array($request->tipo_accion, $tiposConFechaFin);

        $request->validate([
            "tipo_accion"           => "required|in:ENCARGO,SUBROGACION,INGRESO,VACACIONES,DESTITUCION,CESACION DE FUNCIONES",
            "fecha_elaboracion"     => "required|date",
            "id_emp"                => "required|string",
            "fecha_inicio"          => "required|date",
            "fecha_fin"             => ($fechaFinRequerida ? "required" : "nullable") . "|date|after_or_equal:fecha_inicio",
            "motivacion"            => "nullable|string",
            "propuesto_cargo"       => ($conPropuesta ? "required" : "nullable") . "|string|max:200",
            "propuesto_grupo_ocup"  => "nullable|string|max:100",
            "propuesto_grado"       => "nullable|integer",
            "propuesto_remuneracion"=> ($conPropuesta ? "required" : "nullable") . "|numeric|min:0",
            "propuesto_partida"     => "nullable|string|max:60",
            "propuesto_proceso_inst"=> "nullable|string|max:30",
        ]);

        $emp = Empleado::findOrFail($request->id_emp);

        // Generar número de acción: DATH-2026-00001
        $prefijo = Configuracion::where("concepto", "PREFIJO_ACCION_PERSONAL")->value("valor") ?? "DATH";
        $anio    = Carbon::now()->year;
        $ultimo  = AccionPersonal::whereYear("created_at", $anio)->max(
            DB::raw("CAST(SPLIT_PART(numero_accion, '-', 3) AS INTEGER)")
        ) ?? 0;
        $numero  = str_pad($ultimo + 1, 5, "0", STR_PAD_LEFT);
        $numeroAccion = "{$prefijo}-{$anio}-{$numero}";

        $esIngreso    = $request->tipo_accion === 'INGRESO';
        $propuestoRem = (float) $request->propuesto_remuneracion;
        $actualRem    = $esIngreso ? 0.0 : (float) ($emp->sueldo ?? 0);
        $diferencial  = max(0, $propuestoRem - $actualRem);

        $accion = AccionPersonal::create([
            "numero_accion"          => $numeroAccion,
            "tipo_accion"            => $request->tipo_accion,
            "fecha_elaboracion"      => $request->fecha_elaboracion,
            "id_emp"                 => $emp->id_emp,
            "id_emp_titular"         => $request->id_emp_titular ?? null,
            "fecha_inicio"           => $request->fecha_inicio,
            "fecha_fin"              => $request->fecha_fin ?? null,
            "motivacion"             => $request->motivacion,
            // Snapshot situación actual (vacío para INGRESO)
            "actual_cargo"           => $esIngreso ? null : $emp->cargo_empleado,
            "actual_grupo_ocup"      => $esIngreso ? null : $emp->grupo_ocupacional,
            "actual_grado"           => $esIngreso ? null : $emp->nivel,
            "actual_remuneracion"    => $actualRem,
            "actual_partida"         => $esIngreso ? null : ($emp->partida_presupuestaria
                ? ($emp->partida_presupuestaria . ($emp->partida_individual ? "-{$emp->partida_individual}" : ""))
                : null),
            "actual_proceso_inst"    => $esIngreso ? null : $emp->proceso_institucional,
            // Situación propuesta
            "propuesto_cargo"        => $request->propuesto_cargo,
            "propuesto_grupo_ocup"   => $request->propuesto_grupo_ocup,
            "propuesto_grado"        => $request->propuesto_grado,
            "propuesto_remuneracion" => $propuestoRem,
            "propuesto_partida"      => $request->propuesto_partida,
            "propuesto_proceso_inst" => $request->propuesto_proceso_inst,
            "diferencial"            => $diferencial,
            "estado"                 => "ACTIVO",
            "creado_por"             => $request->user()->id_emp,
        ]);

        return response()->json($accion->load(["empleado", "titular"]), 201);
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

        // Al finalizar un encargo manualmente, guardar la fecha de fin ingresada
        if ($request->estado === "FINALIZADO" && $request->filled("fecha_fin")) {
            $data["fecha_fin"] = $request->fecha_fin;
        }

        $accion->update($data);
        return response()->json(["message" => "Estado actualizado correctamente."]);
    }

    // GET /api/acciones-personal/{id}/pdf
    public function pdf($id)
    {
        $accion  = AccionPersonal::with(["empleado.departamento", "titular.departamento"])->findOrFail($id);
        $creador = Empleado::find($accion->creado_por);

        $config = Configuracion::whereIn("concepto", [
            "DIRECTOR_TALENTO_HUMANO",
            "PRESIDENTE_INSTITUCION",
            "APROBADOR_ACCION_PERSONAL",
            "nombre_institucion",
            "PREFIJO_ACCION_PERSONAL",
        ])->pluck("valor", "concepto");

        $logoPath   = public_path("logo.png");
        $logoBase64 = file_exists($logoPath)
            ? "data:image/png;base64," . base64_encode(file_get_contents($logoPath))
            : null;

        $pdf = Pdf::loadView("reportes.accion_personal", [
            "accion"   => $accion,
            "config"   => $config,
            "logo"     => $logoBase64,
            "creador"  => $creador,
        ])->setPaper("letter", "portrait");

        $filename = "accion_personal_{$accion->numero_accion}.pdf";
        return $pdf->download($filename);
    }

    // POST /api/acciones-personal/{id}/subir-firmado
    public function subirFirmado(Request $request, $id)
    {
        $request->validate(["archivo" => "required|file|mimes:pdf|max:20480"]);
        $accion = AccionPersonal::findOrFail($id);

        // Eliminar nodo anterior en Alfresco si existe
        if ($accion->pdf_firmado) {
            Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
                ->delete("{$this->alfrescoBase}/nodes/{$accion->pdf_firmado}");
        }

        $anio     = Carbon::parse($accion->fecha_elaboracion)->year;
        $docLibId = $this->getDocLibNodeId();
        $rootId   = $this->getOrCreateFolderNodeId($docLibId, "acciones-personal");
        $folderId = $this->getOrCreateFolderNodeId($rootId, (string)$anio);
        $archivo  = $request->file("archivo");
        $nombre   = "accion_{$accion->numero_accion}_firmado.pdf";

        $upload = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach("filedata", file_get_contents($archivo->getRealPath()), $nombre)
            ->post("{$this->alfrescoBase}/nodes/{$folderId}/children", [
                "name"       => $nombre,
                "nodeType"   => "cm:content",
                "autoRename" => true,
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
