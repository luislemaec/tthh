<?php
namespace App\Http\Controllers;

use App\Models\Supervisor;
use App\Models\Empleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $emp = $request->user();

        // Verificar si es admin o TH
        $esAdminOTH = DB::table("dbo.admin_usuario_rol as ur")
            ->join("dbo.admin_rol as r", "ur.id_rol", "=", "r.id")
            ->where("ur.id_emp", $emp->id_emp)
            ->whereIn("r.descripcion", ["ADMINISTRADOR", "TALENTO HUMANO"])
            ->exists();

        // Verificar si es supervisor
        $esSupervisor = Supervisor::where("id_supervisor", $emp->id_emp)->exists();

        // Total empleados activos
        $totalActivos = DB::table("dbo.ad_empleado")
            ->where("estado", "ACTIVO")
            ->where("id_depto", "!=", 999)
            ->count();

        // Empleados por departamento
        $porDepartamento = DB::table("dbo.ad_empleado as e")
            ->join("dbo.ad_departamento as d", "e.id_depto", "=", "d.id_depto")
            ->where("e.estado", "ACTIVO")
            ->where("e.id_depto", "!=", 999)
            ->select("d.nombre_depto", DB::raw("count(*) as total"))
            ->groupBy("d.nombre_depto")
            ->orderByDesc("total")
            ->get();

        // Permisos pendientes según rol
        $queryPermisos = DB::table("dbo.d2_permiso as p")
            ->join("dbo.ad_empleado as e", "p.id_emp", "=", "e.id_emp")
            ->where("p.estado_permiso", "PENDIENTE")
            ->where("e.id_depto", "!=", 999);

        if (!$esAdminOTH && $esSupervisor) {
            // Supervisor ve solo pendientes de su área
            $deptos = Supervisor::where("id_supervisor", $emp->id_emp)->pluck("id_depto");
            $queryPermisos->whereIn("e.id_depto", $deptos);
        } elseif (!$esAdminOTH && !$esSupervisor) {
            // Empleado ve solo sus propios pendientes
            $queryPermisos->where("p.id_emp", $emp->id_emp);
        }

        $permisosPendientes = $queryPermisos->count();

        // Vacaciones pendientes según rol
        $queryVacaciones = DB::table("dbo.d2_vacacion as v")
            ->join("dbo.ad_empleado as e", "v.id_emp", "=", "e.id_emp")
            ->where("v.estado_permiso", "PENDIENTE")
            ->where("e.id_depto", "!=", 999);

        if (!$esAdminOTH && $esSupervisor) {
            $deptos = Supervisor::where("id_supervisor", $emp->id_emp)->pluck("id_depto");
            $queryVacaciones->whereIn("e.id_depto", $deptos);
        } elseif (!$esAdminOTH && !$esSupervisor) {
            $queryVacaciones->where("v.id_emp", $emp->id_emp);
        }

        $vacacionesPendientes = $queryVacaciones->count();

        return response()->json([
            "total_activos"         => $totalActivos,
            "por_departamento"      => $porDepartamento,
            "permisos_pendientes"   => $permisosPendientes,
            "vacaciones_pendientes" => $vacacionesPendientes,
            "es_supervisor"         => $esSupervisor,
            "es_admin_th"           => $esAdminOTH,
        ]);
    }
}
