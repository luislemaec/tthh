<?php

namespace App\Services;

use App\Models\Empleado;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class AutorizacionMarcacionService
{
    public function tienePermiso(Empleado $emp): bool
    {
        if (trim($emp->estado ?? '') !== 'ACTIVO') {
            return false;
        }
        $roles = DB::table('dbo.admin_usuario_rol as ur')->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $emp->id_emp)->where('r.estado', true);

        return (clone $roles)->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])->exists()
            || $roles->join('dbo.admin_rol_opcion as ro', 'r.id', '=', 'ro.id_rol')
                ->join('dbo.admin_opcion as o', 'ro.id_opcion', '=', 'o.id')->where('o.estado', true)
                ->where('o.url', 'asistencia')->exists();
    }

    public function bloqueo(Empleado $emp): ?string
    {
        if (! $this->tienePermiso($emp)) {
            return 'No tienes autorización para registrar asistencia.';
        }
        $modalidad = $emp->modalidad_marcacion ?? 'PRESENCIAL';
        if (! in_array($modalidad, ['PRESENCIAL', 'TEMPORAL', 'TELETRABAJO', 'BIOMETRICO'], true)) {
            return 'Tu modalidad de marcación no está configurada correctamente. Contacta a Talento Humano.';
        }
        if ($modalidad === 'BIOMETRICO') {
            return 'Tu marcación es exclusivamente por reloj biométrico.';
        }
        if ($modalidad === 'TELETRABAJO' && ! DB::table('dbo.ad_empleado_teletrabajo')->where('id_emp', $emp->id_emp)
            ->where('fecha_desde', '<=', now()->toDateString())->where('fecha_hasta', '>=', now()->toDateString())->exists()) {
            return 'Tu período de teletrabajo ha vencido o no está habilitado. Contacta a Talento Humano.';
        }

        return null;
    }

    public function autorizar(Empleado $emp): void
    {
        if ($mensaje = $this->bloqueo($emp)) {
            throw new HttpResponseException(response()->json(['message' => $mensaje], 403));
        }
    }
}
