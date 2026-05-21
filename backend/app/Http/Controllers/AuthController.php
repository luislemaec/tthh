<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
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

        if (!$empleado || !Hash::check($request->password, $empleado->password)) {
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

        return response()->json([
            'token'    => $token,
            'empleado' => [
                'id_emp'         => $empleado->id_emp,
                'nombre'         => $empleado->nombre_emp,
                'apellido'       => $empleado->apellido_emp,
                'identificacion' => $empleado->identificacion,
                'departamento'   => $empleado->departamento?->nombre_depto,
                'tipo_contrato'           => $empleado->tipo_contrato,
                'nivel'                   => $empleado->nivel,
                'puede_solicitar_vehiculo'=> (bool) $empleado->puede_solicitar_vehiculo,
                'modalidad_marcacion'     => $empleado->modalidad_marcacion,
                'foto'                    => $empleado->foto,
            ],
            'roles' => $roles,
            'menu'  => $menu,
        ]);
    }
	  public function logout(Request $request)
    {
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
