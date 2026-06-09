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
    private string $alfrescoBase = 'http://192.168.26.38:8080/alfresco/api/-default-/public/alfresco/versions/1';
    private string $alfrescoUser = 'admin';
    private string $alfrescoPass = 'admin';
    private string $alfrescoSite = 'talentohumano';

    private function esAdminOTH(string $idEmp): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'r.id_rol', '=', 'ur.id_rol')
            ->where('ur.id_emp', $idEmp)
            ->whereIn('r.nombre_rol', ['ADMINISTRADOR', 'TALENTO HUMANO'])
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

        $empleado = Empleado::with(['departamento', 'jornada'])->find($request->id_emp);
        if (!$empleado) {
            return response()->json(['message' => 'Empleado no encontrado'], 404);
        }
        if (strtoupper($empleado->estado) !== 'ACTIVO') {
            return response()->json(['message' => 'Solo se pueden emitir certificados para empleados activos'], 422);
        }

        // Generar número correlativo: DATH-CL-NNN-YYYY
        $año   = now()->year;
        $ultimo = DB::table('dbo.d2_certificado_laboral')
            ->whereYear('fecha_emision', $año)
            ->selectRaw("MAX(CAST(SPLIT_PART(numero, '-', 3) AS INTEGER)) as ultimo")
            ->value('ultimo');
        $seq    = str_pad(($ultimo ?? 0) + 1, 3, '0', STR_PAD_LEFT);
        $numero = "DATH-CL-{$seq}-{$año}";

        // Configuración
        $configRows  = DB::table('dbo.d2_configuracion')
            ->whereRaw("LOWER(concepto) IN ('nombre_institucion','firmante_th_nombre','firmante_th_cargo','ciudad_institucion')")
            ->get(['concepto', 'valor']);
        $config      = collect($configRows)->mapWithKeys(fn($r) => [strtolower($r->concepto) => $r->valor]);
        $nombreInst  = $config['nombre_institucion']  ?? 'CONSEJO DE COMUNICACIÓN';
        $firmanteNom = $config['firmante_th_nombre']  ?? '';
        $firmanteCar = $config['firmante_th_cargo']   ?? 'RESPONSABLE DE TALENTO HUMANO';
        $ciudad      = $config['ciudad_institucion']  ?? 'Quito';

        // Fecha de emisión en español
        $meses = ['','enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $hoy   = Carbon::now();
        $fechaStr = $hoy->day . ' de ' . $meses[$hoy->month] . ' de ' . $hoy->year;

        // Fecha de ingreso en español
        $fi     = Carbon::parse($empleado->fecha_ingreso);
        $fechaIngStr = $fi->day . ' de ' . $meses[$fi->month] . ' de ' . $fi->year;

        $logo        = base64_encode(file_get_contents(public_path('logo.png')));
        $generadoPor = trim($actor->apellido_emp) . ' ' . trim($actor->nombre_emp);

        $pdf = Pdf::loadView('reportes.certificado_laboral', compact(
            'empleado', 'numero', 'nombreInst', 'firmanteNom', 'firmanteCar',
            'ciudad', 'fechaStr', 'fechaIngStr', 'logo', 'generadoPor'
        ))->setPaper('a4', 'portrait');

        $pdfContent  = $pdf->output();
        $nombreArch  = "certificado_laboral_{$numero}.pdf";

        // Guardar en BD (sin Alfresco — el firmado se sube manualmente después)
        $cert = CertificadoLaboral::create([
            'numero'          => $numero,
            'id_emp'          => $empleado->id_emp,
            'fecha_emision'   => $hoy->toDateString(),
            'alfresco_id'     => null,
            'nombre_archivo'  => $nombreArch,
            'usuario_emision' => $actor->id_emp,
        ]);

        AuditoriaService::log(
            'dbo.d2_certificado_laboral',
            $cert->id,
            'EMITIR',
            null,
            ['numero' => $numero, 'id_emp' => $empleado->id_emp],
            $request,
            "Certificado laboral {$numero} emitido para {$empleado->apellido_emp} {$empleado->nombre_emp}"
        );

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nombreArch}\"",
            'X-Certificado-Id'    => $cert->id,
            'X-Numero'            => $numero,
        ]);
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

        return response()->json(['message' => 'PDF firmado subido correctamente', 'alfresco_id' => $cert->alfresco_id]);
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
