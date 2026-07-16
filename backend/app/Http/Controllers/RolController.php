<?php
namespace App\Http\Controllers;

use App\Models\AdminRol;
use App\Models\AdminUsuarioRol;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolController extends Controller
{
    public function index()
    {
        return response()->json(AdminRol::where('estado', true)->get());
    }

    public function show($id)
    {
        $opciones = DB::table('dbo.admin_rol_opcion')
            ->where('id_rol', $id)->get();
        $rol = AdminRol::findOrFail($id);
        $rol->opciones = $opciones;
        return response()->json($rol);
    }

    public function store(Request $request)
    {
        $request->validate(['descripcion' => 'required|string|max:50']);
        $rol = AdminRol::create([
            'descripcion' => strtoupper($request->descripcion),
            'estado'      => true,
        ]);
        return response()->json($rol, 201);
    }

    public function update(Request $request, $id)
    {
        $rol = AdminRol::findOrFail($id);
        $rol->update($request->only('descripcion','estado'));
        return response()->json($rol);
    }
	 public function destroy($id)
    {
        AdminRol::findOrFail($id)->update(['estado' => false]);
        return response()->json(['message' => 'Rol desactivado']);
    }

    public function opciones()
    {
        return response()->json(
            DB::table('dbo.admin_opcion')
                ->where('estado', true)
                ->orderBy('orden_categoria')
                ->orderBy('secuencia')
                ->get()
        );
    }

    public function asignarOpciones(Request $request, $id)
    {
        $request->validate(['opciones' => 'required|array']);
        DB::table('dbo.admin_rol_opcion')->where('id_rol', $id)->delete();
        $rows = collect($request->opciones)->map(fn($op) => [
            'id_rol' => $id, 'id_opcion' => $op,
        ])->toArray();
        DB::table('dbo.admin_rol_opcion')->insert($rows);
        return response()->json(['message' => 'Opciones asignadas']);
    }
	  public function asignarRolEmpleado(Request $request, $id_emp)
    {
        $request->validate([
            'id_rol'         => 'required|integer',
            'identificacion' => 'required|string',
        ]);
        $existe = AdminUsuarioRol::where('id_emp', $id_emp)
            ->where('id_rol', $request->id_rol)->exists();
        if (!$existe) {
            AdminUsuarioRol::create([
                'id_emp'         => $id_emp,
                'identificacion' => $request->identificacion,
                'id_rol'         => $request->id_rol,
            ]);
        }
        $rol = AdminRol::find($request->id_rol);
        AuditoriaService::log('dbo.admin_usuario_rol', 0, 'ASIGNAR_ROL',
            null,
            ['id_emp' => $id_emp, 'rol' => $rol?->descripcion],
            $request, "Asignación de rol {$rol?->descripcion} a empleado {$id_emp}");

        return response()->json(['message' => 'Rol asignado']);
    }

    public function rolesEmpleado($id_emp)
    {
        $roles = \App\Models\AdminUsuarioRol::with("rol")
            ->where("id_emp", $id_emp)
            ->get();
        return response()->json($roles);
    }

    public function quitarRolEmpleado(Request $request, $id_emp, $id_rol)
    {
        $rol = AdminRol::find($id_rol);
        AdminUsuarioRol::where('id_emp', $id_emp)
            ->where('id_rol', $id_rol)->delete();
        AuditoriaService::log('dbo.admin_usuario_rol', 0, 'REVOCAR_ROL',
            ['id_emp' => $id_emp, 'rol' => $rol?->descripcion],
            null,
            $request, "Revocación de rol {$rol?->descripcion} a empleado {$id_emp}");
        return response()->json(['message' => 'Rol removido']);
    }
}
