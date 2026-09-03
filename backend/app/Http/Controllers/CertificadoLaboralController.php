<?php
namespace App\Http\Controllers;

use App\Models\CertificadoLaboral;
use App\Models\Empleado;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class CertificadoLaboralController extends Controller
{
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

    private function esAdminOTH(string $idEmp): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $idEmp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])
            ->exists();
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

    // Arma el PDF del certificado (sin firmar) a partir del empleado y el número —
    // extraído para que store() (emisión) y pdf() (re-descarga del sin-firmar, ver
    // hallazgo 2 de la sección Certificados Laborales en CLAUDE.md) usen exactamente
    // la misma plantilla/datos sin duplicar el bloque completo.
    private function construirPdf(Empleado $empleado, string $numero, string $generadoPor)
    {
        $configRows  = DB::table('dbo.d2_configuracion')
            ->whereRaw("LOWER(concepto) IN ('nombre_institucion','firmante_th_nombre','firmante_th_cargo','ciudad_institucion')")
            ->get(['concepto', 'valor']);
        $config      = collect($configRows)->mapWithKeys(fn($r) => [strtolower($r->concepto) => $r->valor]);
        $nombreInst  = $config['nombre_institucion']  ?? 'CONSEJO DE COMUNICACIÓN';
        $firmanteNom = $config['firmante_th_nombre']  ?? '';
        $firmanteCar = $config['firmante_th_cargo']   ?? 'RESPONSABLE DE TALENTO HUMANO';
        $ciudad      = $config['ciudad_institucion']  ?? 'Quito';

        $meses = ['','enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $hoy      = Carbon::now();
        $fechaStr = $hoy->day . ' de ' . $meses[$hoy->month] . ' de ' . $hoy->year;

        $fi          = Carbon::parse($empleado->fecha_ingreso);
        $fechaIngStr = $fi->day . ' de ' . $meses[$fi->month] . ' de ' . $fi->year;

        $activo      = strtoupper($empleado->estado) === 'ACTIVO';
        $fechaSalStr = null;
        if (!$activo && $empleado->fecha_salida) {
            $fs          = Carbon::parse($empleado->fecha_salida);
            $fechaSalStr = $fs->day . ' de ' . $meses[$fs->month] . ' de ' . $fs->year;
        }

        $logo = base64_encode(file_get_contents(public_path('logo.png')));

        return Pdf::loadView('reportes.certificado_laboral', compact(
            'empleado', 'numero', 'nombreInst', 'firmanteNom', 'firmanteCar',
            'ciudad', 'fechaStr', 'fechaIngStr', 'fechaSalStr', 'activo', 'logo', 'generadoPor'
        ))->setPaper('a4', 'portrait');
    }

    // GET /api/certificados-laborales
    public function index(Request $request)
    {
        $actor = $request->user();
        if (!$this->esAdminOTH($actor->id_emp)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $query = CertificadoLaboral::with(['empleado', 'emisor'])
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if ($request->filled('id_emp')) {
            $b = '%' . $request->id_emp . '%';
            $query->whereHas('empleado', fn($q) =>
                $q->whereRaw("(identificacion ILIKE ? OR apellido_emp ILIKE ? OR nombre_emp ILIKE ?)", [$b, $b, $b])
            );
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_emision', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_emision', '<=', $request->fecha_hasta);
        }

        return response()->json($query->paginate(30));
    }

    // POST /api/certificados-laborales
    public function store(Request $request)
    {
        $actor = $request->user();
        if (!$this->esAdminOTH($actor->id_emp)) {
            return response()->json(['message' => 'Solo Talento Humano o Administrador puede emitir certificados'], 403);
        }

        $request->validate([
            'id_emp' => 'required|string',
        ]);

        // Antes buscaba sin filtro — se le podía emitir un certificado laboral a un
        // Funcionario Externo (es_externo=true, sin historial laboral real que certificar)
        // o al placeholder de sistema (depto 999).
        $empleado = Empleado::with(['departamento', 'jornada'])
            ->where('id_depto', '!=', 999)
            ->find($request->id_emp);
        if (!$empleado) {
            return response()->json(['message' => 'Empleado no encontrado'], 404);
        }
        if ($empleado->es_externo) {
            return response()->json(['message' => 'No se emiten certificados laborales a Funcionarios Externos.'], 422);
        }

        $año = now()->year;

        // Generación de número + creación del registro envueltas en transacción: el
        // advisory lock de CertificadoLaboral::generarSiguienteNumero() solo sirve
        // mientras dure la transacción que lo pidió (evita dos emisiones simultáneas
        // calculando el mismo número — antes chocaba contra el UNIQUE con un 500).
        $cert = DB::transaction(function () use ($empleado, $año, $actor) {
            $numero     = CertificadoLaboral::generarSiguienteNumero($año);
            $nombreArch = "certificado_laboral_{$numero}.pdf";

            return CertificadoLaboral::create([
                'numero'          => $numero,
                'id_emp'          => $empleado->id_emp,
                'fecha_emision'   => now()->toDateString(),
                'alfresco_id'     => null,
                'nombre_archivo'  => $nombreArch,
                'usuario_emision' => $actor->id_emp,
                'estado'          => 'EMITIDO',
            ]);
        });

        $generadoPor = trim($actor->apellido_emp) . ' ' . trim($actor->nombre_emp);
        $pdf         = $this->construirPdf($empleado, $cert->numero, $generadoPor);
        $pdfContent  = $pdf->output();

        AuditoriaService::log(
            'dbo.d2_certificado_laboral',
            $cert->id,
            'EMITIR',
            null,
            ['numero' => $cert->numero, 'id_emp' => $empleado->id_emp],
            $request,
            "Certificado laboral {$cert->numero} emitido para {$empleado->apellido_emp} {$empleado->nombre_emp}"
        );

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$cert->nombre_archivo}\"",
            'X-Certificado-Id'    => $cert->id,
            'X-Numero'            => $cert->numero,
        ]);
    }

    // GET /api/certificados-laborales/{id}/pdf — regenera el PDF sin firmar. Antes no
    // existía: store() lo devolvía una sola vez sin persistirlo, así que si TH perdía el
    // archivo antes de firmarlo, la única salida era re-emitir (consumiendo otro número
    // y dejando la fila anterior huérfana con alfresco_id=null). Funciona en cualquier
    // estado (igual que AccionPersonalController::pdf() con sus borradores).
    public function pdf($id, Request $request)
    {
        $actor = $request->user();
        if (!$this->esAdminOTH($actor->id_emp)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $cert     = CertificadoLaboral::with('empleado.departamento')->findOrFail($id);
        $empleado = $cert->empleado;

        $generadoPor = trim($actor->apellido_emp) . ' ' . trim($actor->nombre_emp);
        $pdf         = $this->construirPdf($empleado, $cert->numero, $generadoPor);

        return $pdf->stream($cert->nombre_archivo ?? "certificado_laboral_{$cert->numero}.pdf");
    }

    // POST /api/certificados-laborales/{id}/subir-firmado
    public function subirFirmado($id, Request $request)
    {
        $actor = $request->user();
        if (!$this->esAdminOTH($actor->id_emp)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $request->validate(['archivo' => 'required|file|mimes:pdf|max:10240']);

        $cert = CertificadoLaboral::with('empleado')->findOrFail($id);

        if ($cert->estado === 'ANULADO') {
            return response()->json(['message' => 'Este certificado está anulado, no se puede subir un firmado.'], 422);
        }

        $año         = $cert->fecha_emision->year;
        $cedula      = trim($cert->empleado->identificacion);
        $apellido    = strtoupper(trim($cert->empleado->apellido_emp));
        $carpetaEmp  = "{$cedula}_{$apellido}";
        $nombreArch  = $cert->nombre_archivo ?? "certificado_laboral_{$cert->numero}.pdf";

        $docLibId = $this->getDocLibNodeId();
        $upload   = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach('filedata', file_get_contents($request->file('archivo')->getRealPath()), $nombreArch)
            ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
                'name'         => $nombreArch,
                'nodeType'     => 'cm:content',
                'relativePath' => "certificados-laborales/{$año}/{$carpetaEmp}",
                'autoRename'   => true,
            ]);

        if (!$upload->successful()) {
            return response()->json(['message' => 'No se pudo subir el archivo a Alfresco'], 502);
        }

        $cert->update(['alfresco_id' => $upload->json('entry.id')]);

        AuditoriaService::log(
            'dbo.d2_certificado_laboral',
            $cert->id,
            'SUBIR_FIRMADO',
            null,
            ['numero' => $cert->numero, 'alfresco_id' => $cert->alfresco_id],
            $request,
            "PDF firmado subido para certificado laboral {$cert->numero}"
        );

        return response()->json(['message' => 'PDF firmado subido correctamente', 'alfresco_id' => $cert->alfresco_id]);
    }

    // PATCH /api/certificados-laborales/{id}/anular — antes no existía ninguna forma de
    // invalidar un certificado emitido por error (empleado equivocado, dato mal cargado).
    // El número anulado NO se libera/reutiliza — queda como constancia de que existió y
    // se invalidó (mismo criterio que numero_accion en Acciones de Personal).
    public function anular($id, Request $request)
    {
        $actor = $request->user();
        if (!$this->esAdminOTH($actor->id_emp)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $request->validate(['observacion' => 'required|string|max:300']);

        $cert = CertificadoLaboral::with('empleado')->findOrFail($id);

        if ($cert->estado === 'ANULADO') {
            return response()->json(['message' => 'Este certificado ya está anulado.'], 422);
        }

        $cert->update([
            'estado'                => 'ANULADO',
            'observacion_anulacion' => $request->observacion,
            'anulado_en'            => now(),
            'anulado_por'           => $actor->id_emp,
        ]);

        AuditoriaService::log(
            'dbo.d2_certificado_laboral',
            $cert->id,
            'ANULAR',
            ['estado' => 'EMITIDO'],
            ['estado' => 'ANULADO', 'observacion' => $request->observacion],
            $request,
            "Anulación de certificado laboral {$cert->numero}: " . trim($cert->empleado->apellido_emp . ' ' . $cert->empleado->nombre_emp)
        );

        return response()->json(['message' => 'Certificado anulado correctamente']);
    }

    // GET /api/certificados-laborales/{id}/descargar
    public function descargar($id, Request $request)
    {
        $actor = $request->user();
        if (!$this->esAdminOTH($actor->id_emp)) {
            return response()->json(['message' => 'Sin permisos'], 403);
        }

        $cert = CertificadoLaboral::findOrFail($id);

        if (!$cert->alfresco_id) {
            return response()->json(['message' => 'El PDF firmado no ha sido subido aún'], 404);
        }

        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/nodes/{$cert->alfresco_id}/content");

        if (!$resp->successful()) {
            return response()->json(['message' => 'No se pudo descargar el PDF desde Alfresco'], 502);
        }

        return response($resp->body(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$cert->nombre_archivo}\"",
        ]);
    }
}
