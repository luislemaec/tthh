<?php

namespace App\Services;

use App\Models\Empleado;
use App\Models\SgControlPersona;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarcacionService
{
    public const CONCEPTOS = ['ENTRADA', 'SALIDA AL LUNCH', 'ENTRADA DEL LUNCH', 'SALIDA'];

    public function __construct(private AutorizacionMarcacionService $autorizacion, private UbicacionMarcacionService $ubicacion) {}

    public function registrar(Request $request): SgControlPersona
    {
        $request->validate(['concepto' => 'required|in:'.implode(',', self::CONCEPTOS), 'motivo' => 'nullable|string|max:120']);

        return DB::connection('pgsql')->transaction(function () use ($request) {
            // Web y App comparten el mismo bloqueo; la segunda solicitud vuelve a comprobar duplicados.
            $emp = Empleado::whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
            // Un login puede revocar el token mientras esta solicitud espera el bloqueo.
            $token = $request->user()->currentAccessToken();
            if (! $emp->tokens()->whereKey($token->getKey())->exists()
                || $token->created_at->copy()->addMinutes(15)->lessThanOrEqualTo(now())) {
                $this->rechazar('Tu sesión venció. Ingresa nuevamente.', 401);
            }
            $this->autorizacion->autorizar($emp);
            $movil = $request->user()->currentAccessToken()->name === SesionSitService::APP;
            $modalidad = $emp->modalidad_marcacion ?? 'PRESENCIAL';
            $geo = null;
            if ($modalidad === 'PRESENCIAL') {
                if ($movil) {
                    $geo = $this->ubicacion->validar($request);
                } else {
                    $config = DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'vlans_permitidas'")->value('valor');
                    $vlans = array_filter(array_map('trim', explode(',', $config ?? '')));
                    if (! $vlans || ! collect($vlans)->contains(fn ($v) => str_starts_with($request->ip(), $v))) {
                        $this->rechazar('Solo puede registrar asistencia desde las instalaciones de la institución.', 403);
                    }
                }
            }
            $fechaHora = now();
            $marcaciones = SgControlPersona::where('nro_documento', $emp->id_emp)
                ->whereDate('fecha_hora', $fechaHora->toDateString())->pluck('concepto')->all();
            $concepto = $request->concepto;
            if (in_array($concepto, $marcaciones, true)) {
                $this->rechazar("Ya registraste $concepto hoy", 422);
            }
            $indice = array_search($concepto, self::CONCEPTOS, true);
            if ($indice > 0 && ! in_array(self::CONCEPTOS[$indice - 1], $marcaciones, true)) {
                $this->rechazar('Debes registrar '.self::CONCEPTOS[$indice - 1].' primero', 422);
            }
            $canal = $movil ? 'APP' : 'WEB';
            $marcacion = SgControlPersona::create([
                'identificador' => 0,
                'clasificacion' => in_array($concepto, ['ENTRADA', 'ENTRADA DEL LUNCH']) ? 'ENTRADA' : 'SALIDA',
                'nro_documento' => $emp->id_emp,
                'lugar' => $canal,
                'fecha_hora' => $fechaHora,
                'concepto' => $concepto,
                'motivo' => $request->motivo,
                'tipo_marcacion' => $modalidad === 'TELETRABAJO' ? 'TELETRABAJO' : 'WEB',
                'ip' => $request->ip(),
                'ubicacion' => $emp->ubicacion ?? 'Quito',
                'procesado' => 'NO',
                'origen' => $canal,
            ]);
            AuditoriaService::log('dbo.sg_control_persona', $marcacion->secuencial, 'MARCACION', null,
                ['concepto' => $concepto, 'canal' => $canal, 'ubicacion_validada' => $geo], $request, 'Registro de asistencia');

            return $marcacion;
        });
    }

    private function rechazar(string $mensaje, int $estado): never
    {
        throw new HttpResponseException(response()->json(['message' => $mensaje], $estado));
    }
}
