<?php

namespace App\Services;

use App\Models\Empleado;
use Illuminate\Support\Facades\DB;

class SesionSitService
{
    public const WEB = 'sit:web';

    public const APP = 'sit:app';

    public function emitir(Empleado $empleado, bool $movil): array
    {
        // La fila del empleado serializa logins concurrentes de ambos canales.
        return DB::connection('pgsql')->transaction(function () use ($empleado, $movil) {
            Empleado::whereKey($empleado->getKey())->lockForUpdate()->firstOrFail();
            $canal = $movil ? self::APP : self::WEB;
            $empleado->tokens()->whereIn('name', $movil ? [$canal] : [$canal, 'auth_token'])->delete();
            $expira = now()->addMinutes(15);
            $token = $empleado->createToken($canal, $movil ? ['sit:marcacion'] : ['*'], $expira);

            return ['token' => $token->plainTextToken, 'expires_at' => $expira->toIso8601String()];
        });
    }
}
