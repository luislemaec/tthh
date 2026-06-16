<?php

namespace App\Http\Controllers\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\ComAnticipo;
use App\Models\ComSolicitud;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnticipController extends Controller
{
    public function store(Request $request, int $solicitudId): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($solicitudId);

        if ($solicitud->estado !== 'AUTORIZADO') {
            return response()->json(['message' => 'La comisión debe estar AUTORIZADA'], 422);
        }
        if (!$solicitud->tiene_anticipo) {
            return response()->json(['message' => 'Esta comisión no tiene anticipo habilitado'], 422);
        }
        if ($solicitud->id_emp !== $request->user()->id_emp) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        if ($solicitud->anticipo) {
            return response()->json(['message' => 'Ya existe un anticipo para esta comisión'], 422);
        }

        $request->validate(['monto_solicitado' => 'required|numeric|min:0.01']);

        $anticipo = ComAnticipo::create([
            'solicitud_id'     => $solicitudId,
            'monto_solicitado' => $request->monto_solicitado,
            'estado'           => 'PENDIENTE',
            'created_by'       => $request->user()->id_emp,
            'updated_by'       => $request->user()->id_emp,
        ]);

        AuditoriaService::log('dbo.com_anticipo', $anticipo->id, 'CREAR', null, ['monto' => $request->monto_solicitado], $request, 'Anticipo de viáticos solicitado');

        return response()->json($anticipo, 201);
    }

    public function registrarCurCompromiso(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        return $this->registrarCur($request, $id, 'cur_compromiso', 'PRESUPUESTO', 'CUR_COMPROMISO_ANTICIPO', 'CUR de compromiso del anticipo registrado');
    }

    public function registrarCurDevengado(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        return $this->registrarCur($request, $id, 'cur_devengado', 'CONTABILIDAD', 'CUR_DEVENGADO_ANTICIPO', 'CUR de devengado del anticipo registrado');
    }

    public function confirmarPago(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $anticipo = ComAnticipo::findOrFail($id);

        if (!$this->tieneRol($request, 'TESORERIA') && !$this->tieneRol($request, 'ADMINISTRADOR')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate(['fecha_pago' => 'required|date']);

        $anterior = $anticipo->estado;
        $anticipo->update([
            'estado'     => 'PAGADO',
            'fecha_pago' => $request->fecha_pago,
            'updated_by' => $request->user()->id_emp,
        ]);

        AuditoriaService::log('dbo.com_anticipo', $id, 'PAGAR_ANTICIPO', ['estado' => $anterior], ['estado' => 'PAGADO'], $request, 'Anticipo pagado por Tesorería');

        return response()->json(['estado' => $anticipo->estado]);
    }

    private function registrarCur(Request $request, int $id, string $campo, string $rol, string $accion, string $desc): \Illuminate\Http\JsonResponse
    {
        $anticipo = ComAnticipo::findOrFail($id);

        if (!$this->tieneRol($request, $rol) && !$this->tieneRol($request, 'ADMINISTRADOR')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([$campo => 'required|string|max:50']);

        $anticipo->update([$campo => $request->$campo, 'updated_by' => $request->user()->id_emp]);
        AuditoriaService::log('dbo.com_anticipo', $id, $accion, [], [$campo => $request->$campo], $request, $desc);

        return response()->json(['message' => 'Registrado', $campo => $request->$campo]);
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
