<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        if ($request->routeIs('mobile.login') && app()->environment('production') && ! $request->isSecure()) {
            return response()->json(['message' => 'La autenticación móvil requiere HTTPS.'], 403);
        }
        $request->validate([
            'identificacion' => 'required|string',
            'password'       => 'required|string',
        ]);

        $empleado = Empleado::where('identificacion', $request->identificacion)
            ->where('estado', 'ACTIVO')
            ->first();

        $autenticado = app(\App\Services\AutenticacionService::class)->autenticar($request->identificacion, $request->password);

        if (!$autenticado) {
            // Registrar intento fallido si el empleado existe
            if ($empleado) {
                try {
                    DB::table('dbo.nom_auditoria_log')->insert([
                        'tabla'          => 'auth',
                        'registro_id'    => 0,
                        'accion'         => 'LOGIN_FALLIDO',
                        'datos_nuevos'   => json_encode(['identificacion' => $request->identificacion]),
                        'usuario_id'     => $empleado->id_emp,
                        'nombre_usuario' => trim($empleado->apellido_emp . ' ' . $empleado->nombre_emp),
                        'ip_origen'      => $request->ip(),
                        'descripcion'    => 'Intento de inicio de sesión con contraseña incorrecta',
                        'created_at'     => now(),
                    ]);
                } catch (\Exception) {}
            }
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }
        $empleado = $autenticado;

        $roles = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $empleado->id_emp)
            ->where('r.estado', true)
            ->pluck('r.descripcion');
			 $menu = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol_opcion as ro', 'ur.id_rol', '=', 'ro.id_rol')
            ->join('dbo.admin_opcion as o', 'ro.id_opcion', '=', 'o.id')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $empleado->id_emp)
            ->where('o.estado', true)
            ->where('r.estado', true)
            ->select('o.id','o.descripcion','o.url','o.categoria',
                     'o.orden_categoria','o.secuencia','o.padre')
            ->orderBy('o.orden_categoria')
            ->orderBy('o.secuencia')
            ->get();

        $movil = $request->routeIs('mobile.login');
        $sesion = app(\App\Services\SesionSitService::class)->emitir($empleado, $movil);
        $token = $sesion['token'];

        DB::table('dbo.d2_auditoria')->insert([
            'fecha_hora' => now(),
            'usuario'    => 0,
            'concepto'   => 'LOGIN: '.$empleado->apellido_emp.' '.$empleado->nombre_emp,
            'id_emp'     => $empleado->id_emp,
            'ip'         => $request->ip(),
        ]);

        try {
            DB::table('dbo.nom_auditoria_log')->insert([
                'tabla'          => 'auth',
                'registro_id'    => 0,
                'accion'         => 'LOGIN',
                'datos_nuevos'   => json_encode(['ip' => $request->ip()]),
                'usuario_id'     => $empleado->id_emp,
                'nombre_usuario' => trim($empleado->apellido_emp . ' ' . $empleado->nombre_emp),
                'ip_origen'      => $request->ip(),
                'descripcion'    => 'Inicio de sesión en el sistema',
                'created_at'     => now(),
            ]);
        } catch (\Exception) {}

        if ($movil) {
            return response()->json(array_merge($sesion, $this->mobilePayload($empleado)));
        }
        return response()->json([
            'expires_at' => $sesion['expires_at'],
            'token'    => $token,
            'empleado' => [
                'id_emp'         => $empleado->id_emp,
                'nombre'         => $empleado->nombre_emp,
                'apellido'       => $empleado->apellido_emp,
                'identificacion' => $empleado->identificacion,
                'cargo'          => $empleado->cargo_empleado,
                'departamento'   => $empleado->departamento?->nombre_depto,
                'tipo_contrato'           => $empleado->tipo_contrato,
                'nivel'                   => $empleado->nivel,
                'puede_solicitar_vehiculo'=> (bool) $empleado->puede_solicitar_vehiculo,
                'modalidad_marcacion'     => $empleado->modalidad_marcacion,
                'foto'                    => $empleado->foto,
                'banco'          => $empleado->banco,
                'tipo_cuenta'    => $empleado->tipo_cuenta,
                'numero_cuenta'  => $empleado->numero_cuenta,
            ],
            'roles' => $roles,
            'menu'  => $menu,
        ]);
    }
    public function logout(Request $request)
    {
        AuditoriaService::log(
            'auth', 0, 'LOGOUT', null,
            ['ip' => $request->ip()],
            $request,
            'Cierre de sesión'
        );
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function me(Request $request)
    {
        $emp = $request->user()->load('departamento');

        $menu = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol_opcion as ro', 'ur.id_rol', '=', 'ro.id_rol')
            ->join('dbo.admin_opcion as o', 'ro.id_opcion', '=', 'o.id')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $emp->id_emp)
            ->where('o.estado', true)
            ->where('r.estado', true)
            ->select('o.id','o.descripcion','o.url','o.categoria',
                     'o.orden_categoria','o.secuencia','o.padre')
            ->orderBy('o.orden_categoria')
            ->orderBy('o.secuencia')
            ->get();

        return response()->json(['empleado' => $emp, 'menu' => $menu]);
    }

    public function mobileSession(Request $request)
    {
        return response()->json(array_merge($this->mobilePayload($request->user()), [
            'expires_at' => $request->user()->currentAccessToken()->created_at->copy()->addMinutes(15)->toIso8601String(),
        ]));
    }

    private function mobilePayload(Empleado $emp): array
    {
        $bloqueo = app(\App\Services\AutorizacionMarcacionService::class)->bloqueo($emp);
        return [
            'empleado' => [
                'id_emp' => $emp->id_emp,
                'nombre' => trim($emp->nombre_emp),
                'apellido' => trim($emp->apellido_emp),
                'modalidad_marcacion' => $emp->modalidad_marcacion ?? 'PRESENCIAL',
            ],
            'capacidades' => ['marcacion' => $bloqueo === null],
            'mensaje_bloqueo' => $bloqueo,
        ];
    }
}
