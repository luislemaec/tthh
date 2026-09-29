<?php

namespace App\Http\Controllers\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\ComInforme;
use App\Models\ComInformeTransporte;
use App\Models\ComSolicitud;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class InformeComisionController extends Controller
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

    private function getDocLibNodeId(): string
    {
        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/sites/{$this->alfrescoSite}/containers/documentLibrary");
        if (!$resp->successful()) abort(502, 'No se pudo conectar con Alfresco');
        return $resp->json('entry.id');
    }

    // Dueño de la solicitud, o un rol financiero/administrador que revisa comisiones ajenas
    // (mismo criterio que ComisionController::index() para ver solicitudes de otros).
    private function puedeVerSolicitud(Request $request, string $idEmpDueno): bool
    {
        if ($idEmpDueno === $request->user()->id_emp) return true;
        return $this->tieneAlgunRol($request, [
            'ADMINISTRADOR', 'CONTABILIDAD', 'PRESUPUESTO', 'DIRECTOR FINANCIERO', 'TESORERIA',
        ]);
    }

    public function store(Request $request, int $solicitudId): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($solicitudId);

        if ($solicitud->estado !== 'APROBADO') {
            return response()->json(['message' => 'La comisión debe estar APROBADA para crear el informe'], 422);
        }

        if ($solicitud->id_emp !== $request->user()->id_emp) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($solicitud->informe) {
            return response()->json(['message' => 'Ya existe un informe para esta comisión'], 422);
        }

        $request->validate([
            'fecha_informe' => 'required|date',
            'actividades'   => 'required|string',
            'productos'     => 'nullable|string',
            'fecha_salida'  => 'required|date',
            'hora_salida'   => 'required',
            'fecha_llegada' => 'required|date',
            'hora_llegada'  => 'required',
            'transportes'   => 'array',
        ]);

        DB::beginTransaction();
        try {
            $informe = ComInforme::create([
                'solicitud_id'  => $solicitudId,
                'fecha_informe' => $request->fecha_informe,
                'actividades'   => $request->actividades,
                'productos'     => $request->productos,
                'fecha_salida'  => $request->fecha_salida,
                'hora_salida'   => $request->hora_salida,
                'fecha_llegada' => $request->fecha_llegada,
                'hora_llegada'  => $request->hora_llegada,
                'estado'        => 'PRESENTADO',
                'created_by'    => $request->user()->id_emp,
                'updated_by'    => $request->user()->id_emp,
            ]);

            foreach ($request->transportes ?? [] as $i => $trn) {
                ComInformeTransporte::create([
                    'informe_id'    => $informe->id,
                    'tipo'          => $trn['tipo'] ?? null,
                    'nombre'        => $trn['nombre'] ?? null,
                    'ruta'          => $trn['ruta'] ?? null,
                    'salida_fecha'  => $trn['salida_fecha'] ?? null,
                    'salida_hora'   => $trn['salida_hora'] ?? null,
                    'llegada_fecha' => $trn['llegada_fecha'] ?? null,
                    'llegada_hora'  => $trn['llegada_hora'] ?? null,
                    'orden'         => $i + 1,
                ]);
            }

            // No cambia el estado de la solicitud al crear el informe
            AuditoriaService::log('dbo.com_informe', $informe->id, 'CREAR', null, ['solicitud_id' => $solicitudId], $request, 'Informe de cumplimiento creado');

            DB::commit();
            return response()->json($informe->load('transportes'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Solicitud regresó a estos estados por una devolución financiera, después de que
    // el informe ya había sido APROBADO — son los únicos donde tiene sentido reabrirlo.
    private const ESTADOS_SOLICITUD_CON_REAPERTURA = ['DEVUELTO', 'PROCESADO', 'APROBADO'];

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $informe   = ComInforme::findOrFail($id);
        $solicitud = ComSolicitud::findOrFail($informe->solicitud_id);

        $reabreInformeAprobado = $informe->estado === 'APROBADO'
            && in_array($solicitud->estado, self::ESTADOS_SOLICITUD_CON_REAPERTURA, true);

        if ($informe->estado !== 'PRESENTADO' && !$reabreInformeAprobado) {
            return response()->json(['message' => 'Solo se puede editar un informe en PRESENTADO, o uno ya APROBADO cuando la solicitud está en corrección tras una devolución.'], 422);
        }

        if ($solicitud->id_emp !== $request->user()->id_emp) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'fecha_informe' => 'required|date',
            'actividades'   => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $estadoAnteriorInforme = $informe->estado;

            $informe->update([
                'fecha_informe' => $request->fecha_informe,
                'actividades'   => $request->actividades,
                'productos'     => $request->productos,
                'fecha_salida'  => $request->fecha_salida,
                'hora_salida'   => $request->hora_salida,
                'fecha_llegada' => $request->fecha_llegada,
                'hora_llegada'  => $request->hora_llegada,
                // Si se corrige un informe ya APROBADO, vuelve a PRESENTADO: la firma vieja
                // no debe quedar cubriendo datos ya corregidos, hay que volver a firmarlo.
                'estado'        => $reabreInformeAprobado ? 'PRESENTADO' : $informe->estado,
                'updated_by'    => $request->user()->id_emp,
            ]);

            if ($reabreInformeAprobado) {
                AuditoriaService::log('dbo.com_informe', $informe->id, 'REABRIR',
                    ['estado' => $estadoAnteriorInforme],
                    ['estado' => 'PRESENTADO'],
                    $request, 'Informe reabierto para corrección tras devolución de la solicitud');
            }

            DB::table('dbo.com_informe_transporte')->where('informe_id', $id)->delete();
            foreach ($request->transportes ?? [] as $i => $trn) {
                ComInformeTransporte::create([
                    'informe_id'    => $id,
                    'tipo'          => $trn['tipo'] ?? null,
                    'nombre'        => $trn['nombre'] ?? null,
                    'ruta'          => $trn['ruta'] ?? null,
                    'salida_fecha'  => $trn['salida_fecha'] ?? null,
                    'salida_hora'   => $trn['salida_hora'] ?? null,
                    'llegada_fecha' => $trn['llegada_fecha'] ?? null,
                    'llegada_hora'  => $trn['llegada_hora'] ?? null,
                    'orden'         => $i + 1,
                ]);
            }

            DB::commit();
            return response()->json($informe->load('transportes'));
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function subirFirmado(Request $request, int $solicitudId): \Illuminate\Http\JsonResponse
    {
        $request->validate(['archivo' => 'required|file|mimes:pdf|max:20480']);

        $solicitud = ComSolicitud::findOrFail($solicitudId);

        if ($solicitud->id_emp !== $request->user()->id_emp) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $informe = DB::table('dbo.com_informe')
            ->where('solicitud_id', $solicitudId)
            ->first();

        if (!$informe) abort(404, 'No existe informe para esta solicitud.');

        $emp      = $request->user();
        $anio     = now()->year;
        $apellido = strtoupper(trim($emp->apellido_emp ?? ''));
        $cedula   = $emp->identificacion ?? $emp->id_emp;
        $carpeta  = "comisiones/{$anio}/{$cedula}_{$apellido}";
        $nombre   = 'INFORME_FIRMADO_' . $solicitudId . '_' . now()->format('YmdHis') . '.pdf';

        $docLibId = $this->getDocLibNodeId();
        $upload = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach('filedata', file_get_contents($request->file('archivo')->getRealPath()), $nombre)
            ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
                'name'         => $nombre,
                'nodeType'     => 'cm:content',
                'relativePath' => $carpeta,
                'autoRename'   => true,
            ]);

        if (!$upload->successful()) abort(502, 'No se pudo subir el informe a Alfresco.');

        DB::table('dbo.com_informe')->where('id', $informe->id)->update([
            'pdf_firmado_id'     => $upload->json('entry.id'),
            'pdf_firmado_nombre' => $nombre,
            'estado'             => 'APROBADO',
            'updated_at'         => now(),
        ]);

        DB::table('dbo.com_solicitud')->where('id', $solicitudId)->update([
            'estado'     => 'INFORME_APROBADO',
            'updated_by' => $emp->id_emp,
            'updated_at' => now(),
        ]);

        AuditoriaService::log('dbo.com_informe', $informe->id, 'APROBAR', ['estado' => 'PRESENTADO'], ['estado' => 'APROBADO'], $request, 'Informe aprobado con PDF firmado');

        return response()->json(['message' => 'Informe aprobado.']);
    }

    public function descargarFirmado(Request $request, int $solicitudId)
    {
        $solicitud = ComSolicitud::findOrFail($solicitudId);
        if (!$this->puedeVerSolicitud($request, $solicitud->id_emp)) abort(403, 'No autorizado.');

        $informe = DB::table('dbo.com_informe')
            ->where('solicitud_id', $solicitudId)
            ->first();

        if (!$informe || !$informe->pdf_firmado_id) abort(404, 'No hay informe firmado.');

        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/nodes/{$informe->pdf_firmado_id}/content");

        if (!$resp->successful()) abort(502, 'No se pudo descargar el informe.');

        return response($resp->body(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($informe->pdf_firmado_nombre ?? 'informe.pdf') . '"',
        ]);
    }

    public function pdf(Request $request, int $id)
    {
        $solicitud = ComSolicitud::with(['empleado', 'servidores.empleado', 'informe.transportes'])->findOrFail($id);
        if (!$this->puedeVerSolicitud($request, $solicitud->id_emp)) abort(403, 'No autorizado.');

        $informe   = $solicitud->informe;

        if (!$informe) {
            abort(404, 'Informe no encontrado');
        }

        $logo        = file_exists(public_path('logo.png')) ? base64_encode(file_get_contents(public_path('logo.png'))) : null;
        $generadoPor = trim($solicitud->empleado->apellido_emp ?? '') . ' ' . trim($solicitud->empleado->nombre_emp ?? '');
        $nombreInst  = 'CONSEJO DE COMUNICACIÓN';

        $template = $solicitud->tipo === 'EXTERIOR'
            ? 'reportes.com_informe_exterior'
            : 'reportes.com_informe_interior';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($template, compact(
            'solicitud', 'informe', 'logo', 'nombreInst', 'generadoPor'
        ))->setPaper('a4', 'portrait');

        $numero = $solicitud->numero_solicitud ?? ('COM-' . $id);
        return $pdf->stream("informe-{$numero}.pdf");
    }
}
