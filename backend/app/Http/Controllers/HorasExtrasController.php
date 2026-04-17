<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Supervisor;
use App\Models\Jornada;
use App\Models\HePlanificacionCab;
use App\Models\HePlanificacionDet;
use App\Models\HeRegistro;
use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;

class HorasExtrasController extends Controller
{
    private string $alfrescoBase = 'http://192.168.26.38:8080/alfresco/api/-default-/public/alfresco/versions/1';
    private string $alfrescoUser = 'admin';
    private string $alfrescoPass = 'admin';
    private string $alfrescoSite = 'talentohumano';

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function esAdminOTH($id_emp): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO', 'TALENTO HUMANO NOMINA'])
            ->exists();
    }

    private function esSupervisor($id_emp): bool
    {
        return Supervisor::where('id_supervisor', $id_emp)->exists();
    }

    private function empleadosDeSupervisor($id_supervisor)
    {
        $deptos = Supervisor::where('id_supervisor', $id_supervisor)->pluck('id_depto');
        return Empleado::whereIn('id_depto', $deptos)
            ->where('estado', 'ACTIVO')
            ->where('id_depto', '!=', 999)
            ->pluck('id_emp');
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

    private function getDocLibNodeId(): string
    {
        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/sites/{$this->alfrescoSite}/containers/documentLibrary");
        if (!$resp->successful()) abort(502, 'No se pudo conectar con Alfresco');
        return $resp->json('entry.id');
    }

    // ── PLANIFICACIÓN ──────────────────────────────────────────────────────────

    // GET /api/horas-extras/mi-planificacion?anio=&mes=
    public function miPlanificacion(Request $request)
    {
        $emp  = $request->user();
        $anio = $request->get('anio', now()->year);
        $mes  = $request->get('mes',  now()->month);

        $planificacion = HePlanificacionCab::with('detalles')
            ->where('id_emp', $emp->id_emp)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();

        return response()->json($planificacion);
    }

    // POST /api/horas-extras/planificacion
    public function store(Request $request)
    {
        $request->validate([
            'anio'     => 'required|integer|min:2020',
            'mes'      => 'required|integer|min:1|max:12',
            'detalles' => 'required|array|min:1',
            'detalles.*.actividad'              => 'required|string|max:300',
            'detalles.*.horas_extraordinarias'  => 'nullable|numeric|min:0',
            'detalles.*.horas_suplementarias'   => 'nullable|numeric|min:0',
        ]);

        $emp = $request->user();

        // Verificar que no exista planificación para ese mes
        $existe = HePlanificacionCab::where('id_emp', $emp->id_emp)
            ->where('anio', $request->anio)
            ->where('mes', $request->mes)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya existe una planificación para este mes.'], 422);
        }

        // Calcular totales
        $totalExtraordinarias = 0;
        $totalSupl            = 0;
        foreach ($request->detalles as $det) {
            $totalExtraordinarias += (float)($det['horas_extraordinarias'] ?? 0);
            $totalSupl            += (float)($det['horas_suplementarias']  ?? 0);
        }

        if ($totalExtraordinarias <= 0 && $totalSupl <= 0) {
            return response()->json(['message' => 'Debe ingresar al menos una hora extraordinaria o suplementaria.'], 422);
        }

        $esSupervisorPropio = $this->esSupervisor($emp->id_emp) || $this->esAdminOTH($emp->id_emp);

        $cab = HePlanificacionCab::create([
            'id_emp'                => $emp->id_emp,
            'anio'                  => $request->anio,
            'mes'                   => $request->mes,
            'estado'                => $esSupervisorPropio ? 'APROBADO' : 'PENDIENTE',
            'total_extraordinarias' => $totalExtraordinarias,
            'total_suplementarias'  => $totalSupl,
            'usuario_registro'      => $emp->id_emp,
            'fecha_registro'        => now(),
            'usuario_decision'      => $esSupervisorPropio ? $emp->id_emp : null,
            'fecha_decision'        => $esSupervisorPropio ? now() : null,
        ]);

        foreach ($request->detalles as $det) {
            HePlanificacionDet::create([
                'cab_id'                => $cab->id,
                'actividad'             => $det['actividad'],
                'horas_extraordinarias' => (float)($det['horas_extraordinarias'] ?? 0),
                'horas_suplementarias'  => (float)($det['horas_suplementarias']  ?? 0),
            ]);
        }

        return response()->json($cab->load('detalles'), 201);
    }

    // PUT /api/horas-extras/planificacion/{id}
    public function update(Request $request, $id)
    {
        $cab = HePlanificacionCab::findOrFail($id);
        $emp = $request->user();

        if ($cab->id_emp !== $emp->id_emp && !$this->esAdminOTH($emp->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }
        if ($cab->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se puede editar una planificación en estado PENDIENTE.'], 422);
        }

        $request->validate([
            'detalles' => 'required|array|min:1',
            'detalles.*.actividad'              => 'required|string|max:300',
            'detalles.*.horas_extraordinarias'  => 'nullable|numeric|min:0',
            'detalles.*.horas_suplementarias'   => 'nullable|numeric|min:0',
        ]);

        $totalExtraordinarias = 0;
        $totalSupl            = 0;
        foreach ($request->detalles as $det) {
            $totalExtraordinarias += (float)($det['horas_extraordinarias'] ?? 0);
            $totalSupl            += (float)($det['horas_suplementarias']  ?? 0);
        }

        if ($totalExtraordinarias <= 0 && $totalSupl <= 0) {
            return response()->json(['message' => 'Debe ingresar al menos una hora extraordinaria o suplementaria.'], 422);
        }

        // Reemplazar detalles
        HePlanificacionDet::where('cab_id', $cab->id)->delete();
        foreach ($request->detalles as $det) {
            HePlanificacionDet::create([
                'cab_id'                => $cab->id,
                'actividad'             => $det['actividad'],
                'horas_extraordinarias' => (float)($det['horas_extraordinarias'] ?? 0),
                'horas_suplementarias'  => (float)($det['horas_suplementarias']  ?? 0),
            ]);
        }

        $cab->update([
            'total_extraordinarias' => $totalExtraordinarias,
            'total_suplementarias'  => $totalSupl,
        ]);

        return response()->json($cab->load('detalles'));
    }

    // DELETE /api/horas-extras/planificacion/{id}
    public function destroy(Request $request, $id)
    {
        $cab = HePlanificacionCab::findOrFail($id);
        $emp = $request->user();

        if ($cab->id_emp !== $emp->id_emp && !$this->esAdminOTH($emp->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }
        if ($cab->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se puede eliminar una planificación en estado PENDIENTE.'], 422);
        }

        $cab->delete();
        return response()->json(['message' => 'Planificación eliminada correctamente.']);
    }

    // GET /api/horas-extras/planificacion?anio=&mes=
    public function index(Request $request)
    {
        $emp        = $request->user();
        $esAdmin    = $this->esAdminOTH($emp->id_emp);
        $esSuperv   = $this->esSupervisor($emp->id_emp);
        $anio       = $request->get('anio', now()->year);
        $mes        = $request->get('mes',  now()->month);

        if (!$esAdmin && !$esSuperv) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        $query = HePlanificacionCab::with(['empleado.departamento', 'detalles'])
            ->where('anio', $anio)
            ->where('mes', $mes);

        if (!$esAdmin) {
            // Supervisor solo ve su equipo
            $idsEquipo = $this->empleadosDeSupervisor($emp->id_emp);
            $query->whereIn('id_emp', $idsEquipo);
        }

        $planificaciones = $query->get();
        return response()->json($planificaciones);
    }

    // PATCH /api/horas-extras/planificacion/{id}/aprobar
    public function aprobar(Request $request, $id)
    {
        $cab  = HePlanificacionCab::findOrFail($id);
        $user = $request->user();

        if ($cab->id_emp === $user->id_emp) {
            return response()->json(['message' => 'No puede aprobar su propia planificación.'], 403);
        }
        if ($cab->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se pueden aprobar planificaciones en estado PENDIENTE.'], 422);
        }

        $esAdmin = $this->esAdminOTH($user->id_emp);
        $esSuperv = $this->esSupervisor($user->id_emp);
        if (!$esAdmin && !$esSuperv) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        if (!$esAdmin) {
            $idsEquipo = $this->empleadosDeSupervisor($user->id_emp);
            if (!$idsEquipo->contains($cab->id_emp)) {
                return response()->json(['message' => 'Este empleado no pertenece a su equipo.'], 403);
            }
        }

        $cab->update([
            'estado'           => 'APROBADO',
            'usuario_decision' => $user->id_emp,
            'fecha_decision'   => now(),
            'observacion'      => null,
        ]);

        return response()->json(['message' => 'Planificación aprobada correctamente.']);
    }

    // PATCH /api/horas-extras/planificacion/{id}/negar
    public function negar(Request $request, $id)
    {
        $request->validate(['observacion' => 'required|string|max:250']);
        $cab  = HePlanificacionCab::findOrFail($id);
        $user = $request->user();

        if ($cab->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se pueden negar planificaciones en estado PENDIENTE.'], 422);
        }

        $esAdmin = $this->esAdminOTH($user->id_emp);
        $esSuperv = $this->esSupervisor($user->id_emp);
        if (!$esAdmin && !$esSuperv) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        if (!$esAdmin) {
            $idsEquipo = $this->empleadosDeSupervisor($user->id_emp);
            if (!$idsEquipo->contains($cab->id_emp)) {
                return response()->json(['message' => 'Este empleado no pertenece a su equipo.'], 403);
            }
        }

        $cab->update([
            'estado'           => 'NEGADO',
            'usuario_decision' => $user->id_emp,
            'fecha_decision'   => now(),
            'observacion'      => $request->observacion,
        ]);

        return response()->json(['message' => 'Planificación negada.']);
    }

    // GET /api/horas-extras/planificacion/{id}/pdf
    public function pdf(Request $request, $id)
    {
        $cab    = HePlanificacionCab::with(['detalles', 'empleado.departamento'])->findOrFail($id);
        $emp    = $cab->empleado;
        $jornada = $emp->id_jornada ? Jornada::find($emp->id_jornada) : null;

        $config = Configuracion::whereIn('concepto', [
            'nombre_institucion',
            'DIRECTOR_TALENTO_HUMANO',
        ])->pluck('valor', 'concepto');

        $logoPath   = public_path('logo.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $meses = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];

        $pdf = Pdf::loadView('reportes.he_planificacion', [
            'cab'     => $cab,
            'emp'     => $emp,
            'jornada' => $jornada,
            'config'  => $config,
            'logo'    => $logoBase64,
            'meses'   => $meses,
        ])->setPaper('letter', 'portrait');

        $filename = "horas_extras_{$emp->apellido_emp}_{$emp->nombre_emp}_{$cab->anio}_{$cab->mes}.pdf";
        return $pdf->download($filename);
    }

    // POST /api/horas-extras/planificacion/{id}/subir-firmado
    public function subirFirmado(Request $request, $id)
    {
        $request->validate(['archivo' => 'required|file|mimes:pdf|max:20480']);
        $cab = HePlanificacionCab::findOrFail($id);

        if ($cab->pdf_aprobado) {
            Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
                ->delete("{$this->alfrescoBase}/nodes/{$cab->pdf_aprobado}");
        }

        $anio     = $cab->anio;
        $docLibId = $this->getDocLibNodeId();
        $rootId   = $this->getOrCreateFolderNodeId($docLibId, 'horas-extras');
        $folderId = $this->getOrCreateFolderNodeId($rootId, (string)$anio);

        $emp    = $cab->empleado ?? Empleado::find($cab->id_emp);
        $nombre = "he_{$cab->id_emp}_{$cab->anio}_{$cab->mes}_firmado.pdf";

        $upload = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach('filedata', file_get_contents($request->file('archivo')->getRealPath()), $nombre)
            ->post("{$this->alfrescoBase}/nodes/{$folderId}/children", [
                'name'       => $nombre,
                'nodeType'   => 'cm:content',
                'autoRename' => true,
            ]);

        if (!$upload->successful()) {
            return response()->json(['message' => 'Error al subir el archivo a Alfresco'], 502);
        }

        $cab->update(['pdf_aprobado' => $upload->json('entry.id')]);
        return response()->json(['message' => 'PDF firmado subido correctamente.']);
    }

    // GET /api/horas-extras/planificacion/{id}/descargar-firmado
    public function descargarFirmado($id)
    {
        $cab = HePlanificacionCab::findOrFail($id);
        if (!$cab->pdf_aprobado) {
            return response()->json(['message' => 'No hay PDF firmado disponible.'], 404);
        }

        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/nodes/{$cab->pdf_aprobado}/content");

        if (!$resp->successful()) {
            return response()->json(['message' => 'No se pudo obtener el archivo desde Alfresco'], 502);
        }

        return response($resp->body(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"he_firmado_{$cab->id_emp}_{$cab->anio}_{$cab->mes}.pdf\"",
        ]);
    }

    // ── REGISTRO DE HORAS REALES ───────────────────────────────────────────────

    // GET /api/horas-extras/mis-registros?anio=&mes=
    public function misHoras(Request $request)
    {
        $emp  = $request->user();
        $anio = $request->get('anio', now()->year);
        $mes  = $request->get('mes',  now()->month);

        $cab = HePlanificacionCab::where('id_emp', $emp->id_emp)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();

        $registros = [];
        if ($cab) {
            $registros = HeRegistro::where('cab_id', $cab->id)
                ->orderBy('fecha')
                ->get();
        }

        return response()->json([
            'planificacion' => $cab,
            'registros'     => $registros,
        ]);
    }

    // POST /api/horas-extras/registro
    public function registrar(Request $request)
    {
        $request->validate([
            'cab_id'                => 'required|integer',
            'fecha'                 => 'required|date',
            'horas_extraordinarias' => 'nullable|numeric|min:0',
            'horas_suplementarias'  => 'nullable|numeric|min:0',
            'descripcion'           => 'nullable|string|max:300',
        ]);

        $emp = $request->user();
        $cab = HePlanificacionCab::findOrFail($request->cab_id);

        if ($cab->id_emp !== $emp->id_emp) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }
        if ($cab->estado !== 'APROBADO') {
            return response()->json(['message' => 'La planificación debe estar aprobada para registrar horas.'], 422);
        }

        $nuevasExtraordinarias = (float)($request->horas_extraordinarias ?? 0);
        $nuevasSupl            = (float)($request->horas_suplementarias  ?? 0);

        if ($nuevasExtraordinarias <= 0 && $nuevasSupl <= 0) {
            return response()->json(['message' => 'Debe ingresar al menos una hora extraordinaria o suplementaria.'], 422);
        }

        // Verificar que no supere el total planificado
        $totalAprobadosExtraordinarias = HeRegistro::where('cab_id', $cab->id)
            ->whereIn('estado', ['PENDIENTE', 'APROBADO'])
            ->sum('horas_extraordinarias');
        $totalAprobadosSupl = HeRegistro::where('cab_id', $cab->id)
            ->whereIn('estado', ['PENDIENTE', 'APROBADO'])
            ->sum('horas_suplementarias');

        if (($totalAprobadosExtraordinarias + $nuevasExtraordinarias) > $cab->total_extraordinarias) {
            $disponibles = max(0, $cab->total_extraordinarias - $totalAprobadosExtraordinarias);
            return response()->json([
                'message' => "Las horas extraordinarias superan el límite planificado. Disponible: {$disponibles} h."
            ], 422);
        }
        if (($totalAprobadosSupl + $nuevasSupl) > $cab->total_suplementarias) {
            $disponibles = max(0, $cab->total_suplementarias - $totalAprobadosSupl);
            return response()->json([
                'message' => "Las horas suplementarias superan el límite planificado. Disponible: {$disponibles} h."
            ], 422);
        }

        $registro = HeRegistro::create([
            'cab_id'                => $cab->id,
            'id_emp'                => $emp->id_emp,
            'fecha'                 => $request->fecha,
            'horas_extraordinarias' => $nuevasExtraordinarias,
            'horas_suplementarias'  => $nuevasSupl,
            'descripcion'           => $request->descripcion,
            'estado'                => 'PENDIENTE',
        ]);

        return response()->json($registro, 201);
    }

    // GET /api/horas-extras/equipo-registros?anio=&mes=
    public function equipoHoras(Request $request)
    {
        $user     = $request->user();
        $esAdmin  = $this->esAdminOTH($user->id_emp);
        $esSuperv = $this->esSupervisor($user->id_emp);
        $anio     = $request->get('anio', now()->year);
        $mes      = $request->get('mes',  now()->month);

        if (!$esAdmin && !$esSuperv) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        $query = HeRegistro::with(['empleado', 'planificacion'])
            ->whereHas('planificacion', function ($q) use ($anio, $mes) {
                $q->where('anio', $anio)->where('mes', $mes);
            });

        if (!$esAdmin) {
            $idsEquipo = $this->empleadosDeSupervisor($user->id_emp);
            $query->whereIn('id_emp', $idsEquipo);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        return response()->json($query->orderBy('fecha')->get());
    }

    // PATCH /api/horas-extras/registro/{id}/confirmar
    public function confirmar(Request $request, $id)
    {
        $registro = HeRegistro::findOrFail($id);
        $user     = $request->user();

        if ($registro->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se pueden confirmar registros en estado PENDIENTE.'], 422);
        }

        $esAdmin  = $this->esAdminOTH($user->id_emp);
        $esSuperv = $this->esSupervisor($user->id_emp);
        if (!$esAdmin && !$esSuperv) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        if (!$esAdmin) {
            $idsEquipo = $this->empleadosDeSupervisor($user->id_emp);
            if (!$idsEquipo->contains($registro->id_emp)) {
                return response()->json(['message' => 'Este empleado no pertenece a su equipo.'], 403);
            }
        }

        $registro->update([
            'estado'           => 'APROBADO',
            'usuario_decision' => $user->id_emp,
            'fecha_decision'   => now(),
            'observacion'      => null,
        ]);

        return response()->json(['message' => 'Registro confirmado correctamente.']);
    }

    // PATCH /api/horas-extras/registro/{id}/negar
    public function negarRegistro(Request $request, $id)
    {
        $request->validate(['observacion' => 'required|string|max:250']);
        $registro = HeRegistro::findOrFail($id);
        $user     = $request->user();

        if ($registro->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se pueden negar registros en estado PENDIENTE.'], 422);
        }

        $esAdmin  = $this->esAdminOTH($user->id_emp);
        $esSuperv = $this->esSupervisor($user->id_emp);
        if (!$esAdmin && !$esSuperv) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        if (!$esAdmin) {
            $idsEquipo = $this->empleadosDeSupervisor($user->id_emp);
            if (!$idsEquipo->contains($registro->id_emp)) {
                return response()->json(['message' => 'Este empleado no pertenece a su equipo.'], 403);
            }
        }

        $registro->update([
            'estado'           => 'NEGADO',
            'usuario_decision' => $user->id_emp,
            'fecha_decision'   => now(),
            'observacion'      => $request->observacion,
        ]);

        return response()->json(['message' => 'Registro negado.']);
    }
}
