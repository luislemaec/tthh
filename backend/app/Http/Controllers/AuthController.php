<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Services\ActiveDirectoryService;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'identificacion' => 'required|string',
            'password'       => 'required|string',
        ]);

        $empleado = Empleado::where('identificacion', $request->identificacion)
            ->where('estado', 'ACTIVO')
            ->first();

        // Híbrido: intenta AD primero (cédula = employeeID); si no aplica o falla,
        // cae a la clave local — así los externos sin cuenta AD (es_externo=true)
        // y cualquier ambiente sin AD configurado (ej. pruebas) siguen funcionando igual.
        $autenticado = $empleado && (
            ActiveDirectoryService::autenticar($request->identificacion, $request->password)
            || Hash::check($request->password, $empleado->password)
        );

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

        $token = $empleado->createToken('auth_token')->plainTextToken;

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

        return response()->json([
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
}
