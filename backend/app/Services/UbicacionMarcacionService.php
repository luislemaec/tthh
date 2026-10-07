<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UbicacionMarcacionService
{
    public function parametros(): array
    {
        $parametros = config('marcacion');
        $valores = DB::table('dbo.d2_configuracion')->whereIn('concepto', array_keys($parametros))->pluck('valor', 'concepto');
        foreach ($valores as $clave => $valor) {
            $parametros[$clave] = is_numeric($valor) ? (float) $valor : NAN;
        }

        return $parametros;
    }

    public function desafio(Request $request): string
    {
        return Crypt::encryptString(json_encode([
            'token' => $request->user()->currentAccessToken()->getKey(),
            'issued' => now()->timestamp,
            'nonce' => (string) Str::uuid(),
        ]));
    }

    public static function distancia(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 6371000 * 2 * asin(sqrt(min(1, max(0, $a))));
    }

    public function validar(Request $request): array
    {
        $datos = $request->validate([
            'ubicacion' => 'required|array',
            'ubicacion.latitud' => 'required|numeric|between:-90,90',
            'ubicacion.longitud' => 'required|numeric|between:-180,180',
            'ubicacion.precision' => 'required|numeric|min:0',
            'ubicacion.capturada_en' => 'required|date',
            'ubicacion.simulada' => 'required|boolean|declined',
            'desafio' => 'required|string|max:2048',
        ]);
        $p = $this->parametros();
        if (count(array_filter($p, fn ($v) => ! is_numeric($v) || ! is_finite((float) $v)))
            || abs($p['app_latitud']) > 90 || abs($p['app_longitud']) > 180
            || $p['app_radio_m'] <= 0 || $p['app_precision_m'] <= 0 || $p['app_antiguedad_s'] <= 0) {
            $this->rechazar('La ubicación institucional no está configurada correctamente. Contacta a Talento Humano.');
        }
        try {
            $desafio = json_decode(Crypt::decryptString($datos['desafio']), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->rechazar('La solicitud de ubicación no es válida. Actualiza tu estado e intenta nuevamente.');
        }
        $edad = now()->timestamp - ($desafio['issued'] ?? 0);
        if (($desafio['token'] ?? null) !== $request->user()->currentAccessToken()->getKey()
            || empty($desafio['nonce']) || $edad < 0 || $edad > $p['app_antiguedad_s']) {
            $this->rechazar('La solicitud de ubicación venció. Obtén una ubicación nueva e intenta nuevamente.');
        }
        $u = $datos['ubicacion'];
        $edadLectura = now()->timestamp - Carbon::parse($u['capturada_en'])->timestamp;
        if ($edadLectura < -3) {
            $adelanto = abs($edadLectura);
            $this->rechazar("La fecha de la ubicación está {$adelanto} segundos por delante de SIT. Sincroniza la fecha y hora del celular y de la PC que ejecuta SIT.");
        }
        if ($edadLectura > $p['app_antiguedad_s']) {
            $this->rechazar("La lectura GPS tiene {$edadLectura} segundos de antigüedad; el máximo es {$p['app_antiguedad_s']}. Obtén una lectura nueva. Si las horas difieren, sincroniza el celular y la PC que ejecuta SIT.");
        }
        if ($u['precision'] > $p['app_precision_m']) {
            $this->rechazar('La precisión de la ubicación es insuficiente. Activa la ubicación precisa e intenta nuevamente.');
        }
        $distancia = self::distancia($u['latitud'], $u['longitud'], $p['app_latitud'], $p['app_longitud']);
        if ($distancia + $u['precision'] > $p['app_radio_m']) {
            $this->rechazar('Debes estar dentro de la ubicación institucional autorizada para marcar.');
        }
        if (! Cache::add('marcacion:desafio:'.$desafio['nonce'], true, max(60, (int) $p['app_antiguedad_s'] * 2))) {
            $this->rechazar('La solicitud de ubicación ya fue utilizada. Actualiza tu estado.');
        }

        // Auditoría mínima: no guardar las coordenadas personales del dispositivo.
        return ['distancia_m' => round($distancia, 1), 'precision_m' => (float) $u['precision']];
    }

    private function rechazar(string $mensaje): never
    {
        throw new HttpResponseException(response()->json(['message' => $mensaje], 422));
    }
}
