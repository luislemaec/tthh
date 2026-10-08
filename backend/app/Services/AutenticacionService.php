<?php

namespace App\Services;

use App\Models\Empleado;

class AutenticacionService
{
    public function autenticar(string $identificacion, string $password): ?Empleado
    {
        $empleado = Empleado::where('identificacion', $identificacion)->where('estado', 'ACTIVO')->first();
        if (! $empleado) {
            return null;
        }

        return ActiveDirectoryService::autenticar($identificacion, $password)
            || $this->validarPasswordLocal($password, $empleado->password) ? $empleado : null;
    }

    public function validarPasswordLocal(string $password, ?string $hash): bool
    {
        // PHP reconoce como "bcrypt" solamente el prefijo 2y en password_get_info.
        // PostgreSQL pgcrypto y otros sistemas también generan Bcrypt 2a/2b.
        // Aceptar esas variantes verificables, sin admitir texto plano u otros hashes.
        if (preg_match('/^\$2[aby]\$(?:0[4-9]|[12][0-9]|3[01])\$[.\/A-Za-z0-9]{53}$/D', $hash ?? '') !== 1) {
            return false;
        }

        return password_verify($password, $hash);
    }
}
