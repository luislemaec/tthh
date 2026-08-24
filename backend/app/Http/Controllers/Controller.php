<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    /**
     * Indica si el empleado autenticado tiene alguno de los roles indicados.
     */
    protected function tieneAlgunRol(Request $request, array $roles): bool
    {
        $idEmp = $request->user()?->id_emp;
        if (!$idEmp) {
            return false;
        }

        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $idEmp)
            ->whereIn('r.descripcion', $roles)
            ->exists();
    }

    /**
     * Corta la petición con 403 si el empleado autenticado no tiene ninguno de los roles indicados.
     */
    protected function requireRole(Request $request, array $roles): void
    {
        if (!$this->tieneAlgunRol($request, $roles)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }
    }
}
