<?php

namespace App\Http\Controllers\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\ComInforme;
use App\Models\ComInformeTransporte;
use App\Models\ComSolicitud;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformeComisionController extends Controller
{
    public function store(Request $request, int $solicitudId): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($solicitudId);

        if (!in_array($solicitud->estado, ['AUTORIZADO', 'INFORME_PENDIENTE'])) {
            return response()->json(['message' => 'La comisión debe estar AUTORIZADA para crear el informe'], 422);
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

            $solicitud->update(['estado' => 'INFORME_PRESENTADO', 'updated_by' => $request->user()->id_emp]);

            AuditoriaService::log('dbo.com_informe', $informe->id, 'CREAR', null, ['solicitud_id' => $solicitudId], $request, 'Informe de cumplimiento creado');

            DB::commit();
            return response()->json($informe->load('transportes'), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $informe = ComInforme::findOrFail($id);

        if ($informe->estado !== 'PRESENTADO') {
            return response()->json(['message' => 'Solo se puede editar un informe en PRESENTADO'], 422);
        }

        $solicitud = ComSolicitud::findOrFail($informe->solicitud_id);
        if ($solicitud->id_emp !== $request->user()->id_emp) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'fecha_informe' => 'required|date',
            'actividades'   => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $informe->update([
                'fecha_informe' => $request->fecha_informe,
                'actividades'   => $request->actividades,
                'productos'     => $request->productos,
                'fecha_salida'  => $request->fecha_salida,
                'hora_salida'   => $request->hora_salida,
                'fecha_llegada' => $request->fecha_llegada,
                'hora_llegada'  => $request->hora_llegada,
                'updated_by'    => $request->user()->id_emp,
            ]);

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

    public function revisar(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $informe   = ComInforme::findOrFail($id);
        $solicitud = ComSolicitud::findOrFail($informe->solicitud_id);

        $idEmp = $request->user()->id_emp;
        $esSupervisorDepto = DB::table('dbo.supervisor_area')
            ->where('id_emp', $idEmp)
            ->where('id_depto', $solicitud->id_depto)
            ->exists();

        if (!$esSupervisorDepto && !$this->esAdmin($request)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $informe->update(['estado' => 'REVISADO', 'updated_by' => $idEmp]);
        $solicitud->update(['estado' => 'INFORME_REVISADO', 'updated_by' => $idEmp]);

        AuditoriaService::log('dbo.com_informe', $id, 'REVISAR', ['estado' => 'PRESENTADO'], ['estado' => 'REVISADO'], $request, 'Informe revisado por supervisor');

        return response()->json(['estado' => $informe->estado]);
    }

    public function aprobar(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $informe   = ComInforme::findOrFail($id);
        $solicitud = ComSolicitud::findOrFail($informe->solicitud_id);

        if ($informe->estado !== 'REVISADO') {
            return response()->json(['message' => 'El informe debe estar REVISADO primero'], 422);
        }

        // El jefe del supervisor (máxima autoridad del área o admin)
        if (!$this->esAdmin($request) && !$this->tieneRol($request, 'MAXIMA AUTORIDAD')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $informe->update(['estado' => 'APROBADO', 'updated_by' => $request->user()->id_emp]);
        $solicitud->update(['estado' => 'INFORME_APROBADO', 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.com_informe', $id, 'APROBAR', ['estado' => 'REVISADO'], ['estado' => 'APROBADO'], $request, 'Informe aprobado');

        return response()->json(['estado' => $informe->estado]);
    }

    public function pdf(int $solicitudId)
    {
        $solicitud = ComSolicitud::with(['empleado', 'servidores.empleado', 'informe.transportes'])->findOrFail($solicitudId);
        $informe   = $solicitud->informe;

        if (!$informe) {
            abort(404, 'Informe no encontrado');
        }

        $logo         = file_exists(public_path('logo.png')) ? base64_encode(file_get_contents(public_path('logo.png'))) : null;
        $generadoPor  = trim($solicitud->empleado->apellido_emp ?? '') . ' ' . trim($solicitud->empleado->nombre_emp ?? '');
        $nombreInst   = 'CONSEJO DE COMUNICACIÓN';

        $template = $solicitud->tipo === 'EXTERIOR'
            ? 'reportes.com_informe_exterior'
            : 'reportes.com_informe_interior';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($template, compact(
            'solicitud', 'informe', 'logo', 'nombreInst', 'generadoPor'
        ))->setPaper('a4', 'portrait');

        $numero = $solicitud->numero_solicitud ?? ('COM-' . $solicitudId);
        return $pdf->download("informe-{$numero}.pdf");
    }

    private function esAdmin(Request $request): bool
    {
        $idEmp = $request->user()->id_emp;
        return DB::table('dbo.admin_usuario_rol')
            ->join('dbo.admin_rol', 'admin_usuario_rol.rol_id', '=', 'admin_rol.id')
            ->where('admin_usuario_rol.id_emp', $idEmp)
            ->where('admin_rol.nombre', 'ADMINISTRADOR')
            ->exists();
    }

    private function tieneRol(Request $request, string $rol): bool
    {
        $idEmp = $request->user()->id_emp;
        return DB::table('dbo.admin_usuario_rol')
            ->join('dbo.admin_rol', 'admin_usuario_rol.rol_id', '=', 'admin_rol.id')
            ->where('admin_usuario_rol.id_emp', $idEmp)
            ->where('admin_rol.nombre', $rol)
            ->exists();
    }
}
