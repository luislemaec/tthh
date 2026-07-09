<?php
namespace App\Http\Controllers\Tecnologia;

use App\Http\Controllers\Controller;
use App\Models\Tecnologia\ActividadMantenimiento;
use App\Models\Tecnologia\Asignacion;
use App\Models\Tecnologia\Equipo;
use App\Models\Tecnologia\Mantenimiento;
use App\Models\Tecnologia\MantenimientoDetalle;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MantenimientoController extends Controller
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

    public function checklist()
    {
        return response()->json(ActividadMantenimiento::where('estado', true)->orderBy('orden')->get());
    }

    public function pendientes(Request $request)
    {
        $anio = $request->input('anio', now()->year);

        $query = Equipo::with(['tipoEquipo', 'asignacionActiva.empleado'])
            ->where('estado', '!=', 'DE_BAJA')
            ->whereNotIn('id', function ($q) use ($anio) {
                $q->select('equipo_id')->from('dbo.ti_mantenimiento')->where('anio', $anio);
            })
            ->orderBy('codigo_bien');

        if ($request->filled('tipo_equipo_id')) {
            $query->where('tipo_equipo_id', $request->tipo_equipo_id);
        }
        if ($request->filled('busqueda')) {
            $b = $request->busqueda;
            $query->where(function ($q) use ($b) {
                $q->where('codigo_bien', 'ILIKE', "%$b%")
                  ->orWhere('serie', 'ILIKE', "%$b%")
                  ->orWhere('descripcion', 'ILIKE', "%$b%")
                  ->orWhere('marca', 'ILIKE', "%$b%")
                  ->orWhere('modelo', 'ILIKE', "%$b%");
            });
        }

        return response()->json($query->get());
    }

    public function realizados(Request $request)
    {
        $anio = $request->input('anio', now()->year);

        $query = Mantenimiento::with(['equipo.tipoEquipo', 'tecnico', 'custodio'])
            ->where('anio', $anio)
            ->orderBy('fecha_mantenimiento', 'desc');

        if ($request->filled('tipo_equipo_id')) {
            $query->whereHas('equipo', fn ($q) => $q->where('tipo_equipo_id', $request->tipo_equipo_id));
        }
        if ($request->filled('busqueda')) {
            $b = $request->busqueda;
            $query->whereHas('equipo', function ($q) use ($b) {
                $q->where('codigo_bien', 'ILIKE', "%$b%")
                  ->orWhere('serie', 'ILIKE', "%$b%")
                  ->orWhere('descripcion', 'ILIKE', "%$b%")
                  ->orWhere('marca', 'ILIKE', "%$b%")
                  ->orWhere('modelo', 'ILIKE', "%$b%");
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'equipo_id'           => 'required|exists:pgsql.dbo.ti_equipo,id',
            'fecha_mantenimiento' => 'required|date',
            'hora_inicio'         => 'required',
            'hora_fin'            => 'required',
            'tipo'                => 'nullable|in:PREVENTIVO,CORRECTIVO',
            'observaciones'       => 'nullable|string',
            'checklist'                    => 'required|array|min:1',
            'checklist.*.actividad_id'     => 'required|exists:pgsql.dbo.ti_actividad_mantenimiento,id',
            'checklist.*.realizado'        => 'required|boolean',
        ]);

        $anio = (int) date('Y', strtotime($request->fecha_mantenimiento));

        if (Mantenimiento::where('equipo_id', $request->equipo_id)->where('anio', $anio)->exists()) {
            return response()->json(['message' => 'Este equipo ya tiene un mantenimiento registrado para ese año.'], 422);
        }

        $idEmpCustodio = Asignacion::where('equipo_id', $request->equipo_id)
            ->whereNull('fecha_devolucion')
            ->value('id_emp');

        $mantenimiento = Mantenimiento::create([
            'equipo_id'           => $request->equipo_id,
            'anio'                => $anio,
            'fecha_mantenimiento' => $request->fecha_mantenimiento,
            'hora_inicio'         => $request->hora_inicio,
            'hora_fin'            => $request->hora_fin,
            'tipo'                => $request->tipo ?? 'PREVENTIVO',
            'id_emp_tecnico'      => $request->user()->id_emp,
            'id_emp_custodio'     => $idEmpCustodio,
            'observaciones'       => $request->observaciones,
            'created_by'          => $request->user()->id_emp,
        ]);

        foreach ($request->checklist as $item) {
            MantenimientoDetalle::create([
                'mantenimiento_id' => $mantenimiento->id,
                'actividad_id'     => $item['actividad_id'],
                'realizado'        => $item['realizado'],
            ]);
        }

        Equipo::whereKey($request->equipo_id)->update(['ultimo_mantenimiento' => $request->fecha_mantenimiento]);

        AuditoriaService::log('dbo.ti_mantenimiento', $mantenimiento->id, 'REGISTRAR_MANTENIMIENTO',
            null,
            ['equipo_id' => $request->equipo_id, 'anio' => $anio],
            $request, "Mantenimiento {$anio} registrado para equipo #{$request->equipo_id}");

        return response()->json($mantenimiento->load(['equipo.tipoEquipo', 'tecnico', 'custodio']), 201);
    }

    public function pdf($id)
    {
        $m = Mantenimiento::with(['equipo.tipoEquipo', 'tecnico', 'custodio', 'detalle.actividad'])->findOrFail($id);
        $detalle = $m->detalle->sortBy(fn ($d) => $d->actividad->orden ?? 0)->values();
        $logo = $this->logoBase64();

        $pdf = Pdf::loadView('reportes.ti_acta_mantenimiento', compact('m', 'detalle', 'logo'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('acta_mantenimiento_' . $m->equipo->codigo_bien . '_' . $m->anio . '.pdf');
    }

    public function subirFirmado($id, Request $request)
    {
        $request->validate(['archivo' => 'required|file|mimes:pdf|max:10240']);

        $m = Mantenimiento::with('equipo')->findOrFail($id);

        $nombreArch = 'acta_mantenimiento_' . $m->equipo->codigo_bien . '_' . $m->anio . '.pdf';

        $docLibId = $this->getDocLibNodeId();
        $upload   = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach('filedata', file_get_contents($request->file('archivo')->getRealPath()), $nombreArch)
            ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
                'name'         => $nombreArch,
                'nodeType'     => 'cm:content',
                'relativePath' => "mantenimiento-ti/{$m->anio}",
                'autoRename'   => true,
            ]);

        if (!$upload->successful()) {
            return response()->json(['message' => 'No se pudo subir el archivo a Alfresco'], 502);
        }

        $m->update(['acta_alfresco_id' => $upload->json('entry.id'), 'acta_nombre_archivo' => $nombreArch]);

        return response()->json(['message' => 'Acta firmada subida correctamente', 'acta_alfresco_id' => $m->acta_alfresco_id]);
    }

    public function descargarFirmado($id)
    {
        $m = Mantenimiento::findOrFail($id);

        if (!$m->acta_alfresco_id) {
            return response()->json(['message' => 'El acta firmada no ha sido subida aún'], 404);
        }

        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/nodes/{$m->acta_alfresco_id}/content");

        if (!$resp->successful()) {
            return response()->json(['message' => 'No se pudo descargar el acta desde Alfresco'], 502);
        }

        return response($resp->body(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$m->acta_nombre_archivo}\"",
        ]);
    }

    private function logoBase64(): ?string
    {
        $path = public_path('logo.png');
        return file_exists($path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($path)) : null;
    }
}
