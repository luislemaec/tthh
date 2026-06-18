<?php
namespace App\Http\Controllers\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\ComFuncionarioExterno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FuncionarioExternoController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $lista = ComFuncionarioExterno::orderBy('nombres')->get()->map(function ($f) {
            $tieneAcceso = DB::table('dbo.ad_empleado')
                ->where('id_emp', $f->cedula)
                ->where('estado', 'ACTIVO')
                ->exists();
            return array_merge($f->toArray(), ['tiene_acceso' => $tieneAcceso]);
        });

        return response()->json($lista);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'cedula'  => 'required|string|max:20|unique:pgsql.dbo.com_funcionario_externo,cedula',
            'nombres' => 'required|string|max:200',
            'cargo'   => 'required|string|max:200',
        ]);

        $f = ComFuncionarioExterno::create([
            'cedula'        => $request->cedula,
            'nombres'       => strtoupper($request->nombres),
            'cargo'         => strtoupper($request->cargo),
            'banco'         => $request->banco         ? strtoupper($request->banco) : null,
            'tipo_cuenta'   => $request->tipo_cuenta   ?: null,
            'numero_cuenta' => $request->numero_cuenta ?: null,
            'programa'      => $request->programa      ?: null,
            'actividad'     => $request->actividad     ?: null,
            'activo'        => true,
        ]);

        return response()->json($f, 201);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $f = ComFuncionarioExterno::findOrFail($id);

        $request->validate([
            'nombres' => 'sometimes|string|max:200',
            'cargo'   => 'sometimes|string|max:200',
            'activo'  => 'sometimes|boolean',
        ]);

        $f->update([
            'nombres'       => isset($request->nombres)       ? strtoupper($request->nombres)       : $f->nombres,
            'cargo'         => isset($request->cargo)         ? strtoupper($request->cargo)          : $f->cargo,
            'banco'         => $request->filled('banco')      ? strtoupper($request->banco)          : $f->banco,
            'tipo_cuenta'   => $request->filled('tipo_cuenta')   ? $request->tipo_cuenta            : $f->tipo_cuenta,
            'numero_cuenta' => $request->filled('numero_cuenta') ? $request->numero_cuenta           : $f->numero_cuenta,
            'programa'      => $request->has('programa')      ? ($request->programa ?: null)         : $f->programa,
            'actividad'     => $request->has('actividad')     ? ($request->actividad ?: null)        : $f->actividad,
            'activo'        => $request->has('activo')        ? $request->activo                     : $f->activo,
        ]);

        return response()->json($f);
    }

    public function destroy(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $f = ComFuncionarioExterno::findOrFail($id);
        $f->update(['activo' => false]);

        DB::table('dbo.ad_empleado')
            ->where('id_emp', $f->cedula)
            ->update(['estado' => 'INACTIVO', 'updated_at' => now()]);

        return response()->json(['message' => 'Funcionario desactivado']);
    }

    public function darAcceso(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $request->validate(['password' => 'required|string|min:6']);

        $externo = ComFuncionarioExterno::findOrFail($id);

        // Parsear apellido y nombre del campo "nombres" (formato: APELLIDOS NOMBRES)
        $partes    = explode(' ', trim($externo->nombres), 2);
        $apellido  = $partes[0] ?? '';
        $nombre    = $partes[1] ?? '';

        $data = [
            'identificacion' => $externo->cedula,
            'nombre_emp'     => strtoupper($nombre),
            'apellido_emp'   => strtoupper($apellido),
            'cargo_empleado' => strtoupper($externo->cargo),
            'id_depto'       => 999,
            'estado'         => 'ACTIVO',
            'es_externo'     => true,
            'password'       => bcrypt($request->password),
            'banco'          => $externo->banco,
            'tipo_cuenta'    => $externo->tipo_cuenta,
            'numero_cuenta'  => $externo->numero_cuenta,
            'updated_at'     => now(),
        ];

        $existe = DB::table('dbo.ad_empleado')->where('id_emp', $externo->cedula)->exists();

        if ($existe) {
            DB::table('dbo.ad_empleado')
                ->where('id_emp', $externo->cedula)
                ->update($data);
        } else {
            DB::table('dbo.ad_empleado')->insert(array_merge($data, [
                'id_emp'     => $externo->cedula,
                'created_at' => now(),
            ]));

            $rolId = DB::table('dbo.admin_rol')
                ->where('descripcion', 'COMISIONADO EXTERNO')
                ->value('id');

            if ($rolId) {
                DB::table('dbo.admin_usuario_rol')->insert([
                    'id_emp'  => $externo->cedula,
                    'id_rol'  => $rolId,
                ]);
            }
        }

        return response()->json(['message' => 'Acceso otorgado correctamente.']);
    }
}
