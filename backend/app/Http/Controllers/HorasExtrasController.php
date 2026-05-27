<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Supervisor;
use App\Models\Jornada;
use App\Models\HePlanificacionCab;
use App\Models\HePlanificacionDet;
use App\Models\HeRegistro;
use App\Models\Configuracion;
use App\Services\AuditoriaService;
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

    // ── Cálculo de horas extras ────────────────────────────────────────────────

    private function toMinutes(string $hora): int
    {
        [$h, $m] = explode(':', $hora);
        return (int)$h * 60 + (int)$m;
    }

    private function calcularHorasExtras(string $fecha, string $horaInicio, string $horaFin): array
    {
        $inicio = $this->toMinutes($horaInicio);
        $fin    = $this->toMinutes($horaFin);
        if ($fin === 0) $fin = 1440;        // 00:00 = fin de día
        if ($fin <= $inicio) $fin += 1440;  // cruza medianoche

        $esFeriado = DB::table('dbo.d2_lista_fecha')
            ->whereDate('fecha', $fecha)
            ->exists();

        $carbon = Carbon::parse($fecha);

        $extraordinarias = 0.0;
        $suplementarias  = 0.0;

        if ($carbon->isWeekend() || $esFeriado) {
            $extraordinarias = ($fin - $inicio) / 60;
        } else {
            // Lun-Vie: rangos en minutos
            $rangos = [
                [0,    360,  'extra'],  // 00:00-06:00
                [360,  480,  'supl'],   // 06:00-08:00
                [480,  990,  'normal'], // 08:00-16:30
                [990,  1440, 'supl'],   // 16:30-24:00
                // Para cruces de medianoche (fin > 1440):
                [1440, 1800, 'extra'],  // 00:00-06:00 del día siguiente
                [1800, 1920, 'supl'],   // 06:00-08:00 del día siguiente
            ];
            foreach ($rangos as [$desde, $hasta, $tipo]) {
                $overlap = min($fin, $hasta) - max($inicio, $desde);
                if ($overlap > 0) {
                    if ($tipo === 'extra') $extraordinarias += $overlap / 60;
                    if ($tipo === 'supl')  $suplementarias  += $overlap / 60;
                }
            }
        }

        return [
            'horas_extraordinarias' => round($extraordinarias, 2),
            'horas_suplementarias'  => round($suplementarias,  2),
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function esAdminOTH($id_emp): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO', 'TH NOMINA'])
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

    private function getDocLibNodeId(): string
    {
        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/sites/{$this->alfrescoSite}/containers/documentLibrary");
        if (!$resp->successful()) abort(502, 'No se pudo conectar con Alfresco');
        return $resp->json('entry.id');
    }

    // GET /api/horas-extras/calcular?fecha=&hora_inicio=&hora_fin=
    public function calcular(Request $request)
    {
        $request->validate([
            'fecha'       => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin'    => 'required|date_format:H:i',
        ]);

        return response()->json(
            $this->calcularHorasExtras($request->fecha, $request->hora_inicio, $request->hora_fin)
        );
    }

    // ── ROL ────────────────────────────────────────────────────────────────────

    // GET /api/horas-extras/mi-rol
    public function miRol(Request $request)
    {
        $emp = $request->user();
        return response()->json([
            'es_supervisor' => $this->esSupervisor($emp->id_emp),
            'es_admin_th'   => $this->esAdminOTH($emp->id_emp),
        ]);
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

        if ($totalExtraordinarias > 20) {
            return response()->json(['message' => 'Las horas extraordinarias no pueden superar las 20 horas mensuales.'], 422);
        }
        if ($totalSupl > 20) {
            return response()->json(['message' => 'Las horas suplementarias no pueden superar las 20 horas mensuales.'], 422);
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

        if ($totalExtraordinarias > 20) {
            return response()->json(['message' => 'Las horas extraordinarias no pueden superar las 20 horas mensuales.'], 422);
        }
        if ($totalSupl > 20) {
            return response()->json(['message' => 'Las horas suplementarias no pueden superar las 20 horas mensuales.'], 422);
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

        AuditoriaService::log('dbo.nom_he_planificacion_cab', $cab->id, 'APROBAR',
            ['estado' => 'PENDIENTE'], ['estado' => 'APROBADO'],
            $request, "Aprobación planificación HE: empleado {$cab->id_emp} {$cab->anio}/{$cab->mes}");

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

        AuditoriaService::log('dbo.nom_he_planificacion_cab', $cab->id, 'NEGAR',
            ['estado' => 'PENDIENTE'], ['estado' => 'NEGADO', 'observacion' => $request->observacion],
            $request, "Negación planificación HE: empleado {$cab->id_emp} {$cab->anio}/{$cab->mes}");

        return response()->json(['message' => 'Planificación negada.']);
    }

    // PATCH /api/horas-extras/planificacion/{id}/autorizar
    public function autorizar(Request $request, $id)
    {
        $request->validate(['memorando' => 'required|string|max:300']);
        $user = $request->user();

        if (!$this->esAdminOTH($user->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        $cab = HePlanificacionCab::findOrFail($id);

        if ($cab->estado !== 'APROBADO') {
            return response()->json(['message' => 'Solo se pueden procesar planificaciones en estado APROBADO.'], 422);
        }

        $cab->update([
            'estado'               => 'PROCESADO',
            'memorando'            => $request->memorando,
            'usuario_autorizacion' => $user->id_emp,
            'fecha_autorizacion'   => now(),
        ]);

        AuditoriaService::log('dbo.nom_he_planificacion_cab', $cab->id, 'PROCESAR',
            ['estado' => 'APROBADO'], ['estado' => 'PROCESADO', 'memorando' => $request->memorando],
            $request, "Procesamiento planificación HE: empleado {$cab->id_emp} {$cab->anio}/{$cab->mes}");

        return response()->json(['message' => 'Planificación autorizada.']);
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

        // Buscar supervisor del departamento del empleado
        $supervisorEmp = Supervisor::where('id_depto', $emp->id_depto)->first();
        $supervisorObj = $supervisorEmp ? Empleado::find($supervisorEmp->id_supervisor) : null;
        $nombreSupervisor = $supervisorObj
            ? strtoupper(($supervisorObj->apellido_emp ?? '') . ' ' . ($supervisorObj->nombre_emp ?? ''))
            : ($config['DIRECTOR_TALENTO_HUMANO'] ?? '');

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
            'cab'              => $cab,
            'emp'              => $emp,
            'jornada'          => $jornada,
            'config'           => $config,
            'logo'             => $logoBase64,
            'meses'            => $meses,
            'nombreSupervisor' => $nombreSupervisor,
        ])->setPaper('a4', 'portrait');

        $filename = "horas_extras_{$emp->apellido_emp}_{$emp->nombre_emp}_{$cab->anio}_{$cab->mes}.pdf";
        return $pdf->download($filename);
    }

    // GET /api/horas-extras/planificacion/{id}/pdf-registros
    public function pdfRegistros(Request $request, $id)
    {
        $cab  = HePlanificacionCab::with(['empleado.departamento'])->findOrFail($id);
        $emp  = $cab->empleado;
        $user = $request->user();

        if ($cab->id_emp !== $user->id_emp && !$this->esAdminOTH($user->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        $registros = HeRegistro::where('cab_id', $cab->id)->orderBy('fecha')->get();

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

        $supervisorEmp = Supervisor::where('id_depto', $emp->id_depto)->first();
        $supervisorObj = $supervisorEmp ? Empleado::find($supervisorEmp->id_supervisor) : null;
        $nombreSupervisor = $supervisorObj
            ? strtoupper(($supervisorObj->apellido_emp ?? '') . ' ' . ($supervisorObj->nombre_emp ?? ''))
            : ($config['DIRECTOR_TALENTO_HUMANO'] ?? '');

        $pdf = Pdf::loadView('reportes.he_registros', [
            'cab'             => $cab,
            'emp'             => $emp,
            'registros'       => $registros,
            'config'          => $config,
            'logo'            => $logoBase64,
            'meses'           => $meses,
            'nombreSupervisor' => $nombreSupervisor,
        ])->setPaper('a4', 'portrait');

        $filename = "horas_trabajadas_{$emp->apellido_emp}_{$emp->nombre_emp}_{$cab->anio}_{$cab->mes}.pdf";
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

        $anio         = $cab->anio;
        $docLibId     = $this->getDocLibNodeId();
        $nombre       = "he_{$cab->id_emp}_{$cab->anio}_{$cab->mes}_firmado.pdf";
        $relativePath = "horas-extras/{$anio}";

        $upload = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach('filedata', file_get_contents($request->file('archivo')->getRealPath()), $nombre)
            ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
                'name'         => $nombre,
                'nodeType'     => 'cm:content',
                'relativePath' => $relativePath,
                'autoRename'   => true,
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
            'cab_id'      => 'required|integer',
            'fecha'       => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin'    => 'required|date_format:H:i',
            'descripcion' => 'nullable|string|max:300',
        ]);

        $emp = $request->user();
        $cab = HePlanificacionCab::findOrFail($request->cab_id);

        if ($cab->id_emp !== $emp->id_emp) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }
        if ($cab->estado !== 'PROCESADO') {
            return response()->json(['message' => 'La planificación debe estar procesada para registrar horas.'], 422);
        }

        // Validar que el mes/año actual coincida con el mes/año planificado
        $hoy = now();
        if ((int)$hoy->year !== (int)$cab->anio || (int)$hoy->month !== (int)$cab->mes) {
            $meses = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
                      7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
            $mesNombre = $meses[$cab->mes] ?? $cab->mes;
            return response()->json([
                'message' => "Solo puede registrar horas en el mes planificado ({$mesNombre} {$cab->anio})."
            ], 422);
        }

        // Calcular horas automáticamente
        $calculado = $this->calcularHorasExtras($request->fecha, $request->hora_inicio, $request->hora_fin);
        $nuevasExtraordinarias = $calculado['horas_extraordinarias'];
        $nuevasSupl            = $calculado['horas_suplementarias'];

        if ($nuevasExtraordinarias <= 0 && $nuevasSupl <= 0) {
            return response()->json(['message' => 'El rango horario ingresado no genera horas extras (corresponde a jornada normal).'], 422);
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
            'hora_inicio'           => $request->hora_inicio,
            'hora_fin'              => $request->hora_fin,
            'horas_extraordinarias' => $nuevasExtraordinarias,
            'horas_suplementarias'  => $nuevasSupl,
            'descripcion'           => $request->descripcion,
            'estado'                => 'EN REVISION',
        ]);

        return response()->json($registro, 201);
    }

    // PUT /api/horas-extras/registro/{id} — empleado edita cuando está EN REVISION
    public function actualizarRegistro(Request $request, $id)
    {
        $request->validate([
            'fecha'       => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin'    => 'required|date_format:H:i',
            'descripcion' => 'nullable|string|max:300',
        ]);

        $emp      = $request->user();
        $registro = HeRegistro::findOrFail($id);

        if ($registro->id_emp !== $emp->id_emp) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }
        if ($registro->estado !== 'EN REVISION') {
            return response()->json(['message' => 'Solo puede editar registros en estado EN REVISIÓN.'], 422);
        }

        $calculado = $this->calcularHorasExtras($request->fecha, $request->hora_inicio, $request->hora_fin);

        $registro->update([
            'fecha'                 => $request->fecha,
            'hora_inicio'           => $request->hora_inicio,
            'hora_fin'              => $request->hora_fin,
            'horas_extraordinarias' => $calculado['horas_extraordinarias'],
            'horas_suplementarias'  => $calculado['horas_suplementarias'],
            'descripcion'           => $request->descripcion,
            'observacion'           => null,
        ]);

        return response()->json($registro);
    }

    // PATCH /api/horas-extras/registro/{id}/revisar — TH NOMINA aprueba revisión o devuelve
    public function revisarRegistro(Request $request, $id)
    {
        $request->validate([
            'accion'      => 'required|in:aprobar,devolver',
            'observacion' => 'nullable|string|max:250',
        ]);

        $user = $request->user();
        if (!$this->esAdminOTH($user->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        $registro = HeRegistro::findOrFail($id);
        if ($registro->estado !== 'EN REVISION') {
            return response()->json(['message' => 'Solo se pueden revisar registros en estado EN REVISIÓN.'], 422);
        }

        if ($request->accion === 'aprobar') {
            $registro->update([
                'estado'           => 'PENDIENTE',
                'observacion'      => null,
                'usuario_decision' => $user->id_emp,
                'fecha_decision'   => now(),
            ]);
            return response()->json(['message' => 'Registro aprobado en revisión. Pasa al supervisor.']);
        }

        if (!$request->filled('observacion')) {
            return response()->json(['message' => 'Debe indicar qué debe corregir el empleado.'], 422);
        }

        $registro->update([
            'estado'      => 'EN REVISION',
            'observacion' => $request->observacion,
        ]);
        return response()->json(['message' => 'Registro devuelto al empleado para corrección.']);
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

        $registros = $query->orderBy('fecha')->get();

        // Agregar desglose monetario para registros APROBADO (solo visible para TH NOMINA/Admin)
        if ($esAdmin) {
            $registros = $registros->map(function ($reg) {
                if ($reg->estado === 'APROBADO') {
                    $emp     = $reg->empleado;
                    $jornada = $emp->id_jornada ? Jornada::find($emp->id_jornada) : null;
                    $sueldo  = (float)($emp->sueldo ?? 0);
                    $tarifa  = $sueldo > 0 ? $sueldo / 240 : 0;
                    $pExtra  = $jornada ? (float)$jornada->porc_extraordinaria : 0;
                    $pSupl   = $jornada ? (float)$jornada->porc_suplementaria  : 0;
                    $reg->valor_extraordinarias = round($tarifa * (1 + $pExtra / 100) * (float)$reg->horas_extraordinarias, 2);
                    $reg->valor_suplementarias  = round($tarifa * (1 + $pSupl  / 100) * (float)$reg->horas_suplementarias,  2);
                    $reg->valor_total           = round($reg->valor_extraordinarias + $reg->valor_suplementarias, 2);
                }
                return $reg;
            });
        }

        return response()->json($registros);
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

        AuditoriaService::log('dbo.nom_he_registro', $registro->id, 'CONFIRMAR',
            ['estado' => 'PENDIENTE'], ['estado' => 'APROBADO'],
            $request, "Confirmación registro HE: empleado {$registro->id_emp} fecha {$registro->fecha}");

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

        AuditoriaService::log('dbo.nom_he_registro', $registro->id, 'NEGAR',
            ['estado' => 'PENDIENTE'], ['estado' => 'NEGADO', 'observacion' => $request->observacion],
            $request, "Negación registro HE: empleado {$registro->id_emp} fecha {$registro->fecha}");

        return response()->json(['message' => 'Registro negado.']);
    }
}
