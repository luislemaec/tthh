<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\PlanificacionCab;
use App\Models\ReportePlanificacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportePlanificacionController extends Controller
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

    // ── Helpers ─────────────────────────────────────────────────────────────

    // Obtiene el nodeId del DocumentLibrary del sitio talentohumano
    private function getDocLibNodeId(): string
    {
        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/sites/{$this->alfrescoSite}/containers/documentLibrary");

        if (!$resp->successful()) {
            abort(502, 'No se pudo conectar con Alfresco');
        }

        return $resp->json('entry.id');
    }

    // Obtiene o crea una carpeta por año dentro del DocumentLibrary
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
                'name'      => $folderName,
                'nodeType'  => 'cm:folder',
            ]);

        if ($create->status() === 409) {
            $found = $buscarPorNombre($parentNodeId, $folderName);
            if ($found) return $found;
        }

        if (!$create->successful()) {
            abort(502, 'No se pudo crear la carpeta en Alfresco');
        }

        return $create->json('entry.id');
    }

    // Empleados elegibles para planificar (activos, >= 11 meses)
    private function empleadosElegibles(int $anio): \Illuminate\Support\Collection
    {
        $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
        $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();

        return Empleado::where('estado', 'ACTIVO')
            ->whereNotNull('fecha_ingreso')
            ->where('id_depto', '!=', 999)
            ->get()
            ->filter(function ($emp) use ($fechaCorte) {
                $ingreso = Carbon::parse($emp->fecha_ingreso);
                if ($ingreso->lte($fechaCorte)) return true;  // empleado existente
                return $ingreso->diffInMonths(Carbon::today()) >= 11;
            });
    }

    // ── Estado del reporte (qué falta aprobar) ───────────────────────────────

    public function estado(Request $request, int $anio)
    {
        $empleados      = $this->empleadosElegibles($anio);
        $planificaciones = PlanificacionCab::where('anio', $anio)
            ->whereIn('id_emp', $empleados->pluck('id_emp'))
            ->get()
            ->keyBy('id_emp');

        $departamentos = Departamento::orderBy('nombre_depto')->get();

        $resultado = $departamentos->map(function ($depto) use ($empleados, $planificaciones) {
            $empsDepto = $empleados->where('id_depto', $depto->id_depto)->values();
            if ($empsDepto->isEmpty()) return null;

            $empsData = $empsDepto->map(function ($emp) use ($planificaciones) {
                $plan = $planificaciones->get($emp->id_emp);
                return [
                    'id_emp'      => $emp->id_emp,
                    'nombre'      => $emp->apellido_emp . ', ' . $emp->nombre_emp,
                    'estado_plan' => $plan ? $plan->estado : null,
                    'plan_id'     => $plan?->id,
                ];
            });

            $totalEmp           = $empsData->count();
            $aprobados          = $empsData->whereIn('estado_plan', ['APROBADO', 'REPLANIFICADO'])->count();
            $pendientes         = $empsData->where('estado_plan', 'PENDIENTE')->count();
            $sinPlan            = $empsData->whereNull('estado_plan')->count();
            $tieneReplanificados = $empsData->where('estado_plan', 'REPLANIFICADO')->count() > 0;

            return [
                'id_depto'            => $depto->id_depto,
                'nombre_depto'        => $depto->nombre_depto,
                'total'               => $totalEmp,
                'aprobados'           => $aprobados,
                'pendientes'          => $pendientes,
                'sin_plan'            => $sinPlan,
                'completo'            => $aprobados === $totalEmp,
                'tiene_replanificados'=> $tieneReplanificados,
                'empleados'           => $empsData->values(),
            ];
        })->filter()->values();

        $todoAprobado = $resultado->every(fn($d) => $d['completo']);

        // Info del PDF firmado almacenado (si existe)
        $reporte = ReportePlanificacion::where('anio', $anio)->first();

        return response()->json([
            'anio'          => $anio,
            'todo_aprobado' => $todoAprobado,
            'departamentos' => $resultado,
            'reporte'       => $reporte,
        ]);
    }

    // ── Generar PDF ──────────────────────────────────────────────────────────

    public function generarPdf(Request $request, int $anio)
    {
        $empleados       = $this->empleadosElegibles($anio);
        $planificaciones = PlanificacionCab::with('periodos')
            ->where('anio', $anio)
            ->whereIn('estado', ['APROBADO', 'REPLANIFICADO'])
            ->whereIn('id_emp', $empleados->pluck('id_emp'))
            ->get()
            ->keyBy('id_emp');

        // Verificar que todos estén aprobados
        foreach ($empleados as $emp) {
            if (!$planificaciones->has($emp->id_emp)) {
                return response()->json([
                    'message' => 'No todos los empleados tienen su planificación aprobada. Verifique el estado del reporte.'
                ], 422);
            }
        }

        $coordinador = optional(Configuracion::find('APROBADOR_INST_VACACION'))->valor ?? 'Coordinador General Administrativo Financiero';
        $fechaHoy    = Carbon::now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        // Agrupar por departamento
        $departamentos = Departamento::orderBy('nombre_depto')->get();
        $grupos = $departamentos->map(function ($depto) use ($empleados, $planificaciones) {
            $emps = $empleados->where('id_depto', $depto->id_depto)->sortBy('apellido_emp')->values();
            if ($emps->isEmpty()) return null;

            $filas = $emps->map(function ($emp) use ($planificaciones) {
                $plan     = $planificaciones->get($emp->id_emp);
                $periodos = collect(range(1, 4))->map(function ($n) use ($plan) {
                    return $plan?->periodos->firstWhere('numero_periodo', $n);
                });
                return [
                    'nombre'   => $emp->apellido_emp . ', ' . $emp->nombre_emp,
                    'periodos' => $periodos,
                    'total'    => $plan?->total_dias_planificados ?? 0,
                ];
            });

            return [
                'nombre_depto' => $depto->nombre_depto,
                'filas'        => $filas,
            ];
        })->filter()->values();

        $pdf = Pdf::loadView('reportes.planificacion_vacaciones', [
            'anio'        => $anio,
            'grupos'      => $grupos,
            'coordinador' => $coordinador,
            'fechaHoy'    => $fechaHoy,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("planificacion_vacaciones_{$anio}.pdf");
    }

    // ── Subir PDF firmado a Alfresco ─────────────────────────────────────────

    public function subirFirmado(Request $request, int $anio)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:pdf|max:10240',
        ]);

        $docLibId     = $this->getDocLibNodeId();
        $archivo      = $request->file('archivo');
        $nombre       = "planificacion_vacaciones_{$anio}_firmado.pdf";
        $relativePath = "planificacion-vacaciones/{$anio}";

        // Eliminar nodo anterior si existe
        $existente = ReportePlanificacion::where('anio', $anio)->first();
        if ($existente) {
            Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
                ->delete("{$this->alfrescoBase}/nodes/{$existente->alfresco_node_id}");
        }

        // Subir a Alfresco
        $upload = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach('filedata', file_get_contents($archivo->getRealPath()), $nombre)
            ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
                'name'         => $nombre,
                'nodeType'     => 'cm:content',
                'relativePath' => $relativePath,
                'autoRename'   => true,
            ]);

        if (!$upload->successful()) {
            return response()->json(['message' => 'Error al subir el archivo a Alfresco'], 502);
        }

        $nodeId = $upload->json('entry.id');

        // Guardar referencia
        ReportePlanificacion::updateOrCreate(
            ['anio' => $anio],
            [
                'alfresco_node_id' => $nodeId,
                'nombre_archivo'   => $nombre,
                'fecha_subida'     => now(),
                'subido_por'       => $request->user()->id_emp,
            ]
        );

        return response()->json(['message' => 'Archivo subido correctamente', 'node_id' => $nodeId]);
    }

    // ── Descargar PDF firmado desde Alfresco ─────────────────────────────────

    public function descargarFirmado(Request $request, int $anio)
    {
        $reporte = ReportePlanificacion::where('anio', $anio)->firstOrFail();

        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/nodes/{$reporte->alfresco_node_id}/content");

        if (!$resp->successful()) {
            return response()->json(['message' => 'No se pudo obtener el archivo desde Alfresco'], 502);
        }

        return response($resp->body(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$reporte->nombre_archivo}\"",
        ]);
    }
}
