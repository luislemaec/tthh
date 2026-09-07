<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZktecoController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR'];

    private function dispositivoAutorizado(Request $request): bool
    {
        $sn = $request->query('SN', '');
        if (!$sn) return false;
        return DB::table('dbo.d2_zkteco_dispositivo')
            ->where('serial', $sn)
            ->where('activo', true)
            ->exists();
    }

    // Respuesta de error en el formato de texto plano que espera el protocolo
    // ADMS (no la pagina HTML de Laravel), para que el reloj reintente despues.
    private function errorAdms(\Throwable $e, string $origen)
    {
        \Log::error("ZKTeco {$origen} error: " . $e->getMessage());
        return response('ERROR', 500)->header('Content-Type', 'text/plain');
    }

    // Registra el contacto de un dispositivo SIN auto-activarlo — antes esto ponía
    // 'activo' => true en cada handshake/registro, lo que anulaba en segundos cualquier
    // desactivación manual del admin (bastaba con que el reloj, o cualquiera falsificando
    // su SN, volviera a tocar el endpoint). Ahora: dispositivo nuevo entra INACTIVO
    // (requiere que el admin lo active desde admin/zkteco); dispositivo ya conocido solo
    // actualiza ip/ultimo_push, 'activo' queda tal como lo dejó el admin.
    private function registrarContacto(Request $request, string $sn): void
    {
        $existe = DB::table('dbo.d2_zkteco_dispositivo')->where('serial', $sn)->exists();

        if ($existe) {
            DB::table('dbo.d2_zkteco_dispositivo')
                ->where('serial', $sn)
                ->update(['ip' => $request->ip(), 'ultimo_push' => now(), 'updated_at' => now()]);
        } else {
            DB::table('dbo.d2_zkteco_dispositivo')->insert([
                'serial'      => $sn,
                'ip'          => $request->ip(),
                'ultimo_push' => now(),
                'activo'      => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    // GET|POST /iclock/cdata
    public function cdata(Request $request)
    {
        $sn = $request->query('SN', '');

        // GET = handshake inicial — el reloj pide opciones del servidor
        if ($request->isMethod('get')) {
            if (!$sn) return response('ERROR', 400)->header('Content-Type', 'text/plain');

            try {
                $this->registrarContacto($request, $sn);
            } catch (\Throwable $e) {
                return $this->errorAdms($e, 'cdata-handshake');
            }

            $opts  = "GET OPTION FROM: {$sn}\r\n";
            $opts .= "Stamp=0\r\n";
            $opts .= "OpStamp=0\r\n";
            $opts .= "ATTLOGStamp=0\r\n";
            $opts .= "OPERLOGStamp=0\r\n";
            $opts .= "ATTPHOTOStamp=0\r\n";
            $opts .= "ErrorDelay=30\r\n";
            $opts .= "Delay=10\r\n";
            $opts .= "TransTimes=00:00;14:05\r\n";
            $opts .= "TransInterval=1\r\n";
            $opts .= "TransFlag=TransData AttLog\r\n";
            $opts .= "Realtime=1\r\n";
            $opts .= "Encrypt=0\r\n";
            return response($opts, 200)->header('Content-Type', 'text/plain');
        }

        // POST = marcaciones reales
        try {
            if (!$this->dispositivoAutorizado($request)) {
                return response('ERROR', 403)->header('Content-Type', 'text/plain');
            }

            DB::table('dbo.d2_zkteco_dispositivo')
                ->where('serial', $sn)
                ->update(['ultimo_push' => now(), 'ip' => $request->ip()]);

            $secuencia = ['ENTRADA', 'SALIDA AL LUNCH', 'ENTRADA DEL LUNCH', 'SALIDA'];
            $lineas    = array_filter(explode("\n", trim($request->getContent())));

            foreach ($lineas as $linea) {
                $campos = explode("\t", trim($linea));
                if (count($campos) < 2) continue;

                $pin      = trim($campos[0]);
                $fechaHora = trim($campos[1]);

                if (!$pin || !$fechaHora) continue;

                // ZKTeco trata el PIN como número y elimina ceros iniciales
                // Cédulas ecuatorianas son 10 dígitos — completar si llegan 9
                if (strlen($pin) === 9 && is_numeric($pin)) {
                    $pin = '0' . $pin;
                }

                // Validar formato de fecha
                if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fechaHora)) continue;

                // Buscar empleado activo por cédula (identificacion), no por id_emp
                $empleado = DB::table('dbo.ad_empleado')
                    ->where('identificacion', $pin)
                    ->where('estado', 'ACTIVO')
                    ->first(['id_emp', 'nombre_emp', 'apellido_emp']);

                if (!$empleado) continue;

                $idEmp = $empleado->id_emp;

                // Ignorar duplicados exactos (por id_emp + fecha_hora)
                $yaExiste = DB::table('dbo.sg_control_persona')
                    ->where('nro_documento', $idEmp)
                    ->where('fecha_hora', $fechaHora)
                    ->exists();

                if ($yaExiste) continue;

                // Determinar siguiente concepto en la secuencia del día
                $fecha = substr($fechaHora, 0, 10);
                $marcacionesHoy = DB::table('dbo.sg_control_persona')
                    ->where('nro_documento', $idEmp)
                    ->whereRaw("DATE(fecha_hora) = ?", [$fecha])
                    ->orderBy('fecha_hora')
                    ->pluck('concepto')
                    ->toArray();

                $siguiente = null;
                foreach ($secuencia as $concepto) {
                    if (!in_array($concepto, $marcacionesHoy)) {
                        $siguiente = $concepto;
                        break;
                    }
                }

                // Ya tiene las 4 marcaciones del día
                if (!$siguiente) continue;

                $clasificacion = in_array($siguiente, ['ENTRADA', 'ENTRADA DEL LUNCH']) ? 'ENTRADA' : 'SALIDA';

                DB::table('dbo.sg_control_persona')->insert([
                    'nro_documento'  => $idEmp,
                    'identificador'  => 0,
                    'clasificacion'  => $clasificacion,
                    'lugar'          => 'BIOMETRICO',
                    'concepto'       => $siguiente,
                    'fecha_hora'     => $fechaHora,
                    'tipo_marcacion' => 'BIOMETRICO',
                    'ip'             => $request->ip(),
                    'procesado'      => 'NO',
                ]);
            }

            return response('OK', 200)->header('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            return $this->errorAdms($e, 'cdata-post');
        }
    }

    // GET /iclock/getrequest — polling del reloj
    public function getrequest(Request $request)
    {
        try {
            if (!$this->dispositivoAutorizado($request)) {
                return response('ERROR', 403)->header('Content-Type', 'text/plain');
            }

            $sn       = $request->query('SN', '');
            $cacheKey = "zkteco_last_query_{$sn}";

            // Enviar DATA QUERY cada 2 minutos para traer nuevas marcaciones
            if (!\Cache::has($cacheKey)) {
                \Cache::put($cacheKey, true, now()->addMinutes(2));
                $cmd = "C:1:DATA QUERY table=attlog startTime=2000-01-01 00:00:00 endTime=2099-12-31 23:59:59\r\n";
                return response($cmd, 200)->header('Content-Type', 'text/plain');
            }

            return response('', 200)->header('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            return $this->errorAdms($e, 'getrequest');
        }
    }

    // GET|POST /iclock/registry — registro inicial del dispositivo al arrancar
    public function registry(Request $request)
    {
        try {
            $sn = $request->query('SN', $request->input('SN', 'DESCONOCIDO'));

            $this->registrarContacto($request, $sn);

            return response('OK', 200)->header('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            return $this->errorAdms($e, 'registry');
        }
    }

    // GET /iclock/ping
    public function ping(Request $request)
    {
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    // POST /iclock/devicecmd — confirmación de ejecución de comandos (ignorar)
    public function devicecmd(Request $request)
    {
        try {
            if (!$this->dispositivoAutorizado($request)) {
                return response('ERROR', 403)->header('Content-Type', 'text/plain');
            }
            return response('OK', 200)->header('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            return $this->errorAdms($e, 'devicecmd');
        }
    }

    // ── Admin ────────────────────────────────────────────────────────────────

    // GET /api/admin/zkteco
    // GET /api/admin/zkteco — antes (junto con update/destroy) sin ningún requireRole(), pese a
    // que la vista (ZktecoView.vue, "solo rol ADMINISTRADOR" según CLAUDE.md) asumía que ya
    // estaba cerrado. Cualquier autenticado podía activar/desactivar o eliminar el registro del
    // reloj biométrico llamando directo a la API — activar un serial no autorizado le habría
    // permitido inyectar marcaciones falsas vía el protocolo ADMS (ver ZktecoController::cdata).
    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $dispositivos = DB::table('dbo.d2_zkteco_dispositivo')
            ->orderBy('created_at')
            ->get();
        return response()->json($dispositivos);
    }

    // PUT /api/admin/zkteco/{id}
    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            'nombre' => 'nullable|string|max:100',
            'activo' => 'required|boolean',
        ]);

        $anterior = DB::table('dbo.d2_zkteco_dispositivo')->where('id', $id)->first();

        DB::table('dbo.d2_zkteco_dispositivo')
            ->where('id', $id)
            ->update([
                'nombre'     => $request->nombre,
                'activo'     => $request->activo,
                'updated_at' => now(),
            ]);

        AuditoriaService::log('dbo.d2_zkteco_dispositivo', $id, 'ACTUALIZAR',
            $anterior ? ['nombre' => $anterior->nombre, 'activo' => $anterior->activo] : null,
            ['nombre' => $request->nombre, 'activo' => $request->activo],
            $request, "Actualización dispositivo ZKTeco #{$id}" . ($request->activo ? ' (activado)' : ' (desactivado)'));

        return response()->json(['message' => 'Dispositivo actualizado']);
    }

    // DELETE /api/admin/zkteco/{id}
    public function destroy(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $anterior = DB::table('dbo.d2_zkteco_dispositivo')->where('id', $id)->first();

        DB::table('dbo.d2_zkteco_dispositivo')->where('id', $id)->delete();

        AuditoriaService::log('dbo.d2_zkteco_dispositivo', $id, 'ELIMINAR',
            $anterior ? ['serial' => $anterior->serial, 'nombre' => $anterior->nombre] : null,
            null, $request, "Eliminación dispositivo ZKTeco #{$id}");

        return response()->json(['message' => 'Dispositivo eliminado']);
    }
}
