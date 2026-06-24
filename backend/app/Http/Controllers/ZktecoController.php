<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZktecoController extends Controller
{
    private function dispositivoAutorizado(Request $request): bool
    {
        $sn = $request->query('SN', '');
        if (!$sn) return false;
        return DB::table('dbo.d2_zkteco_dispositivo')
            ->where('serial', $sn)
            ->where('activo', true)
            ->exists();
    }

    // GET|POST /iclock/cdata
    public function cdata(Request $request)
    {
        $sn = $request->query('SN', '');

        // GET = handshake inicial — el reloj pide opciones del servidor
        if ($request->isMethod('get')) {
            if (!$sn) return response('ERROR', 400)->header('Content-Type', 'text/plain');

            // Registrar automáticamente si no existe
            DB::table('dbo.d2_zkteco_dispositivo')
                ->updateOrInsert(
                    ['serial' => $sn],
                    ['ip' => $request->ip(), 'ultimo_push' => now(), 'activo' => true]
                );

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
        if (!$this->dispositivoAutorizado($request)) {
            return response('ERROR', 403)->header('Content-Type', 'text/plain');
        }

        DB::table('dbo.d2_zkteco_dispositivo')
            ->where('serial', $sn)
            ->update(['ultimo_push' => now(), 'ip' => $request->ip()]);

        // LOG TEMPORAL — ver qué envía el reloj
        \Log::info('ZKTECO POST', [
            'table'  => $request->query('table'),
            'body'   => $request->getContent(),
        ]);

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

            // Buscar empleado activo con ese PIN (cédula)
            $empleado = DB::table('dbo.ad_empleado')
                ->where('id_emp', $pin)
                ->where('estado', 'ACTIVO')
                ->first(['id_emp', 'nombre_emp', 'apellido_emp']);

            if (!$empleado) continue;

            // Ignorar duplicados exactos
            $yaExiste = DB::table('dbo.sg_control_persona')
                ->where('nro_documento', $pin)
                ->where('fecha_hora', $fechaHora)
                ->exists();

            if ($yaExiste) continue;

            // Determinar siguiente concepto en la secuencia del día
            $fecha = substr($fechaHora, 0, 10);
            $marcacionesHoy = DB::table('dbo.sg_control_persona')
                ->where('nro_documento', $pin)
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
                'nro_documento'  => $pin,
                'nombre'         => strtoupper(trim($empleado->apellido_emp)) . ' ' . trim($empleado->nombre_emp),
                'clasificacion'  => $clasificacion,
                'concepto'       => $siguiente,
                'fecha_hora'     => $fechaHora,
                'tipo_marcacion' => 'BIOMETRICO',
                'ip'             => $request->ip(),
                'procesado'      => 'NO',
            ]);
        }

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    // GET /iclock/getrequest — polling del reloj (sin comandos pendientes)
    public function getrequest(Request $request)
    {
        if (!$this->dispositivoAutorizado($request)) {
            return response('ERROR', 403)->header('Content-Type', 'text/plain');
        }
        return response('', 200)->header('Content-Type', 'text/plain');
    }

    // GET|POST /iclock/registry — registro inicial del dispositivo al arrancar
    public function registry(Request $request)
    {
        $sn = $request->query('SN', $request->input('SN', 'DESCONOCIDO'));

        DB::table('dbo.d2_zkteco_dispositivo')
            ->updateOrInsert(
                ['serial' => $sn],
                ['ip' => $request->ip(), 'ultimo_push' => now(), 'activo' => true]
            );

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    // GET /iclock/ping
    public function ping(Request $request)
    {
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    // POST /iclock/devicecmd — confirmación de ejecución de comandos (ignorar)
    public function devicecmd(Request $request)
    {
        if (!$this->dispositivoAutorizado($request)) {
            return response('ERROR', 403)->header('Content-Type', 'text/plain');
        }
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    // ── Admin ────────────────────────────────────────────────────────────────

    // GET /api/admin/zkteco
    public function index()
    {
        $dispositivos = DB::table('dbo.d2_zkteco_dispositivo')
            ->orderBy('created_at')
            ->get();
        return response()->json($dispositivos);
    }

    // PUT /api/admin/zkteco/{id}
    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'nullable|string|max:100',
            'activo' => 'required|boolean',
        ]);

        DB::table('dbo.d2_zkteco_dispositivo')
            ->where('id', $id)
            ->update([
                'nombre'     => $request->nombre,
                'activo'     => $request->activo,
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Dispositivo actualizado']);
    }

    // DELETE /api/admin/zkteco/{id}
    public function destroy($id)
    {
        DB::table('dbo.d2_zkteco_dispositivo')->where('id', $id)->delete();
        return response()->json(['message' => 'Dispositivo eliminado']);
    }
}
