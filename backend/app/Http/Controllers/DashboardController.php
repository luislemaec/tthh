<?php
namespace App\Http\Controllers;

use App\Models\Supervisor;
use App\Models\Empleado;
use App\Models\CabeceraVacacion;
use App\Models\Configuracion;
use Carbon\Carbon;
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

        // Datos exclusivos para supervisores (no admin/TH)
        $datosSupervisor = null;
        if ($esSupervisor && !$esAdminOTH) {
            $deptos = Supervisor::where("id_supervisor", $emp->id_emp)->pluck("id_depto");
            $empleadosIds = DB::table("dbo.ad_empleado")
                ->whereIn("id_depto", $deptos)
                ->where("estado", "ACTIVO")
                ->where("id_depto", "!=", 999)
                ->pluck("id_emp");

            $hoy = now()->toDateString();

            $datosSupervisor = [
                "total_equipo"          => $empleadosIds->count(),
                "he_pendientes"         => DB::table("dbo.nom_he_planificacion_cab")
                                            ->whereIn("id_emp", $empleadosIds)
                                            ->where("estado", "PENDIENTE")
                                            ->count(),
                "materiales_pendientes" => DB::table("adq.solicitud_material")
                                            ->whereIn("id_emp", $empleadosIds)
                                            ->where("estado", "PENDIENTE")
                                            ->count(),
                "presentes_hoy"         => DB::table("dbo.sg_control_persona")
                                            ->whereIn("nro_documento", $empleadosIds)
                                            ->whereDate("fecha_hora", $hoy)
                                            ->where("concepto", "ENTRADA")
                                            ->distinct()
                                            ->count("nro_documento"),
                "con_permiso_hoy"       => DB::table("dbo.d2_permiso")
                                            ->whereIn("id_emp", $empleadosIds)
                                            ->where("estado_permiso", "APROBADO")
                                            ->whereDate("fecha_desde", "<=", $hoy)
                                            ->whereDate("fecha_hasta", ">=", $hoy)
                                            ->distinct()
                                            ->count("id_emp"),
                "con_vacaciones_hoy"    => DB::table("dbo.d2_vacacion")
                                            ->whereIn("id_emp", $empleadosIds)
                                            ->where("estado_permiso", "APROBADO")
                                            ->whereDate("fecha_inicial", "<=", $hoy)
                                            ->whereDate("fecha_final", ">=", $hoy)
                                            ->distinct()
                                            ->count("id_emp"),
                "atrasos_mes"           => DB::table("dbo.d2_cuadre_marcacion")
                                            ->whereIn("id_emp", $empleadosIds)
                                            ->whereMonth("fecha", now()->month)
                                            ->whereYear("fecha", now()->year)
                                            ->where(function ($q) {
                                                $q->where("atraso_entrada", ">", 0)
                                                  ->orWhere("atraso_lunch", ">", 0);
                                            })
                                            ->count(),
            ];
        }

        // Datos exclusivos para empleado sin rol especial
        $datosEmpleado = null;
        if (!$esAdminOTH && !$esSupervisor) {
            $tasas = ['LOSEP' => 2.50, 'CODIGO DEL TRABAJO' => 1.25];
            $tasa  = $tasas[trim($emp->tipo_contrato ?? '')] ?? 0;

            $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
            $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();
            if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte)) {
                $fechaCorte = Carbon::parse($emp->fecha_ingreso);
            }
            $diasCalendario = max(0, $fechaCorte->diffInDays(Carbon::today()));
            $diasAcumulados = round($diasCalendario / 360 * ($tasa * 12), 2);

            $cabecera = CabeceraVacacion::where('id_emp', $emp->id_emp)->first();
            $tomados  = (float) ($cabecera->total_dias_tomados ?? 0);
            $adicional= (float) ($cabecera->dias_adicionales   ?? 0);
            $saldo    = max(0, round($adicional + $diasAcumulados - $tomados, 2));

            // Atrasos por mes: días con atraso en cada mes del año actual
            $anio = now()->year;
            $atrasosPorMes = array_fill(1, 12, 0);
            $rows = DB::table('dbo.d2_cuadre_marcacion')
                ->where('id_emp', $emp->id_emp)
                ->whereYear('fecha', $anio)
                ->where(function ($q) {
                    $q->where('atraso_entrada', '>', 0)
                      ->orWhere('atraso_lunch', '>', 0);
                })
                ->selectRaw('EXTRACT(MONTH FROM fecha)::int as mes, COUNT(*) as dias')
                ->groupByRaw('EXTRACT(MONTH FROM fecha)::int')
                ->get();
            foreach ($rows as $row) {
                $atrasosPorMes[$row->mes] = (int) $row->dias;
            }

            $datosEmpleado = [
                'saldo_vacaciones' => $saldo,
                'atrasos_por_mes'  => array_values($atrasosPorMes),
            ];
        }

        return response()->json([
            "total_activos"         => $totalActivos,
            "por_departamento"      => $porDepartamento,
            "permisos_pendientes"   => $permisosPendientes,
            "vacaciones_pendientes" => $vacacionesPendientes,
            "es_supervisor"         => $esSupervisor,
            "es_admin_th"           => $esAdminOTH,
            "datos_supervisor"      => $datosSupervisor,
            "datos_empleado"        => $datosEmpleado,
        ]);
    }
}
