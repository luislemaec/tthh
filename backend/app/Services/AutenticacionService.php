<?php

namespace App\Services;

use App\Models\Empleado;
use Illuminate\Support\Facades\Hash;

class AutenticacionService
{
    public function autenticar(string $identificacion, string $password): ?Empleado
    {
        $empleado = Empleado::where('identificacion', $identificacion)->where('estado', 'ACTIVO')->first();
        if (! $empleado) {
            return null;
        }

        return ActiveDirectoryService::autenticar($identificacion, $password)
            || Hash::check($password, $empleado->password ?? '') ? $empleado : null;
    }
}
