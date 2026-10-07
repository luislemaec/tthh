<?php

namespace App\Http\Middleware;

use App\Services\SesionSitService;
use Closure;
use Illuminate\Http\Request;

class ValidarSesionSit
{
    public function handle(Request $request, Closure $next)
    {
        $emp = $request->user();
        $token = $emp?->currentAccessToken();
        if (! $token || ! $token->created_at || $token->created_at->copy()->addMinutes(15)->lessThanOrEqualTo(now())
            || trim($emp->estado ?? '') !== 'ACTIVO') {
            return response()->json(['message' => 'Tu sesión venció o ya no está habilitada. Ingresa nuevamente.'], 401);
        }

        if ($token->name === SesionSitService::APP) {
            if (app()->environment('production') && ! $request->isSecure()) {
                return response()->json(['message' => 'La sesión móvil requiere HTTPS.'], 403);
            }
            $permitidas = ['api/mobile/session', 'api/logout', 'api/asistencia/mi-estado', 'api/asistencia/marcar'];
            if (! $token->can('sit:marcacion') || ! in_array($request->path(), $permitidas, true)) {
                return response()->json(['message' => 'Esta sesión móvil no permite esa operación.'], 403);
            }
        } elseif ($request->path() === 'api/mobile/session') {
            return response()->json(['message' => 'Se requiere una sesión móvil.'], 403);
        }

        return $next($request);
    }
}
