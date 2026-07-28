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
    // Misma lógica que PermisosController::empleadosDeSupervisor()
    private function empleadosDeSupervisor(string $id_supervisor)
    {
        $deptos = Supervisor::where("id_supervisor", $id_supervisor)->pluck("id_depto");

        $empleadosDirectos = Empleado::whereIn("id_depto", $deptos)
            ->where("estado", "ACTIVO")
            ->where("id_emp", "!=", $id_supervisor)
            ->pluck("id_emp");

        $deptosHijos = DB::table("dbo.ad_departamento")
            ->whereIn("padre_id", $deptos)
            ->pluck("id_depto");

        $supervisoresHijos = Supervisor::whereIn("id_depto", $deptosHijos)
            ->where("id_supervisor", "!=", $id_supervisor)
            ->pluck("id_supervisor");

        return $empleadosDirectos->merge($supervisoresHijos)->unique()->values();
    }

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
            $idsEquipo = $this->empleadosDeSupervisor($emp->id_emp);
            $queryPermisos->whereIn("p.id_emp", $idsEquipo);
        } elseif (!$esAdminOTH && !$esSupervisor) {
            $queryPermisos->where("p.id_emp", $emp->id_emp);
        }

        $permisosPendientes = $queryPermisos->count();

        // Vacaciones pendientes según rol
        $queryVacaciones = DB::table("dbo.d2_vacacion as v")
            ->join("dbo.ad_empleado as e", "v.id_emp", "=", "e.id_emp")
            ->where("v.estado_permiso", "PENDIENTE")
            ->where("e.id_depto", "!=", 999);

        if (!$esAdminOTH && $esSupervisor) {
            $idsEquipo = $idsEquipo ?? $this->empleadosDeSupervisor($emp->id_emp);
            $queryVacaciones->whereIn("v.id_emp", $idsEquipo);
        } elseif (!$esAdminOTH && !$esSupervisor) {
            $queryVacaciones->where("v.id_emp", $emp->id_emp);
        }

        $vacacionesPendientes = $queryVacaciones->count();

        // Datos exclusivos para supervisores (no admin/TH)
        $datosSupervisor = null;
        if ($esSupervisor && !$esAdminOTH) {
            $empleadosIds = $idsEquipo ?? $this->empleadosDeSupervisor($emp->id_emp);

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

            $hoyLimite = now()->addDays(60)->toDateString();
            $vacProximas = DB::table('dbo.vac_planificacion_det as det')
                ->join('dbo.vac_planificacion_cab as cab', 'det.cab_id', '=', 'cab.id')
                ->join('dbo.ad_empleado as e', 'cab.id_emp', '=', 'e.id_emp')
                ->whereIn('cab.id_emp', $empleadosIds)
                ->whereIn('cab.estado', ['APROBADO', 'REPLANIFICADO'])
                ->where('cab.anio', now()->year)
                ->whereNotNull('det.fecha_inicial')
                ->whereNotNull('det.fecha_final')
                ->where('det.fecha_final', '>=', $hoy)
                ->where('det.fecha_inicial', '<=', $hoyLimite)
                ->orderBy('det.fecha_inicial', 'asc')
                ->select('e.apellido_emp', 'e.nombre_emp', 'det.numero_periodo', 'det.fecha_inicial', 'det.fecha_final', 'det.dias_calculados')
                ->get();

            $datosSupervisor['vacaciones_proximas_count'] = $vacProximas->count();
            $datosSupervisor['vacaciones_proximas'] = $vacProximas->map(fn($v) => [
                'nombre'         => trim($v->apellido_emp) . ' ' . trim($v->nombre_emp),
                'numero_periodo' => $v->numero_periodo,
                'fecha_inicial'  => $v->fecha_inicial,
                'fecha_final'    => $v->fecha_final,
                'dias'           => round((float) $v->dias_calculados, 1),
                'en_curso'       => $v->fecha_inicial <= $hoy,
            ])->values();
        }

        // Datos exclusivos para empleado sin rol especial
        $datosEmpleado = null;
        if (!$esAdminOTH && !$esSupervisor) {
            $contrato = trim($emp->tipo_contrato ?? '');
            if ($contrato === 'LOSEP') {
                $tasaMensual = 2.50;
                $diasAdicAntig = 0;
            } elseif ($contrato === 'CODIGO DEL TRABAJO') {
                $anios         = $emp->fecha_ingreso ? (int) Carbon::parse($emp->fecha_ingreso)->diffInYears(Carbon::today()) : 0;
                $diasAdicAntig = min(max(0, $anios - 5), 15);
                $tasaMensual   = (15 + $diasAdicAntig) / 12;
            } else {
                $tasaMensual = 0; $diasAdicAntig = 0;
            }

            $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
            $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();
            if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte)) {
                $fechaCorte = Carbon::parse($emp->fecha_ingreso);
            }
            $diasCalendario = max(0, $fechaCorte->diffInDays(Carbon::today()));
            $diasAcumulados = round($diasCalendario / 360 * ($tasaMensual * 12), 2);

            $cabecera = CabeceraVacacion::where('id_emp', $emp->id_emp)->first();
            $tomados  = (float) ($cabecera->total_dias_tomados ?? 0);
            $adicional= (float) ($cabecera->dias_adicionales   ?? 0);
            $saldo    = min(60, max(0, round($adicional + $diasAcumulados - $tomados, 2)));

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
                'saldo_vacaciones'            => $saldo,
                'atrasos_por_mes'             => array_values($atrasosPorMes),
                'dias_adicionales_antiguedad' => $diasAdicAntig,
            ];

            $hoyEmp = now()->toDateString();
            $periodoProximo = DB::table('dbo.vac_planificacion_det as det')
                ->join('dbo.vac_planificacion_cab as cab', 'det.cab_id', '=', 'cab.id')
                ->where('cab.id_emp', $emp->id_emp)
                ->whereIn('cab.estado', ['APROBADO', 'REPLANIFICADO'])
                ->where('cab.anio', now()->year)
                ->whereNotNull('det.fecha_inicial')
                ->whereNotNull('det.fecha_final')
                ->where('det.fecha_final', '>=', $hoyEmp)
                ->orderBy('det.fecha_inicial', 'asc')
                ->select('det.numero_periodo', 'det.fecha_inicial', 'det.fecha_final', 'det.dias_calculados')
                ->first();

            $datosEmpleado['proximo_periodo'] = $periodoProximo ? [
                'numero_periodo' => $periodoProximo->numero_periodo,
                'fecha_inicial'  => $periodoProximo->fecha_inicial,
                'fecha_final'    => $periodoProximo->fecha_final,
                'dias'           => round((float) $periodoProximo->dias_calculados, 1),
                'en_curso'       => $periodoProximo->fecha_inicial <= $hoyEmp,
            ] : null;
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

    public function pendientesSupervisor(Request $request)
    {
        $emp = $request->user();

        $esAdminOTH = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $emp->id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])
            ->exists();

        $esSupervisor = Supervisor::where('id_supervisor', $emp->id_emp)->exists();

        if (!$esAdminOTH && !$esSupervisor) {
            return response()->json(['permisos' => 0, 'vacaciones' => 0, 'horas_extras' => 0, 'materiales' => 0]);
        }

        $empleadosIds = null;
        if (!$esAdminOTH && $esSupervisor) {
            $empleadosIds = $this->empleadosDeSupervisor($emp->id_emp);
        }

        $qPermisos = DB::table('dbo.d2_permiso as p')
            ->join('dbo.ad_empleado as e', 'p.id_emp', '=', 'e.id_emp')
            ->where('p.estado_permiso', 'PENDIENTE')
            ->where('e.id_depto', '!=', 999);
        if ($empleadosIds) $qPermisos->whereIn('p.id_emp', $empleadosIds);

        $qVacaciones = DB::table('dbo.d2_vacacion as v')
            ->join('dbo.ad_empleado as e', 'v.id_emp', '=', 'e.id_emp')
            ->where('v.estado_permiso', 'PENDIENTE')
            ->where('e.id_depto', '!=', 999);
        if ($empleadosIds) $qVacaciones->whereIn('v.id_emp', $empleadosIds);

        $qHE = DB::table('dbo.nom_he_planificacion_cab')->where('estado', 'PENDIENTE');
        if ($empleadosIds) $qHE->whereIn('id_emp', $empleadosIds);

        $qMat = DB::table('adq.solicitud_material')->where('estado', 'PENDIENTE');
        if ($empleadosIds) $qMat->whereIn('id_emp', $empleadosIds);

        return response()->json([
            'permisos'     => $qPermisos->count(),
            'vacaciones'   => $qVacaciones->count(),
            'horas_extras' => $qHE->count(),
            'materiales'   => $qMat->count(),
        ]);
    }

    public function atrasosCoordinacion(Request $request)
    {
        $emp   = $request->user();
        $anio  = now()->year;
        $nMes  = now()->month;
        $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        $resultado = DB::select("
            SELECT EXTRACT(MONTH FROM c.fecha)::int AS mes,
                   COUNT(*) AS dias
            FROM dbo.d2_cuadre_marcacion c
            WHERE c.id_emp = ?
              AND EXTRACT(YEAR FROM c.fecha)::int = ?
              AND (
                (c.atraso_entrada > 0 AND NOT EXISTS (
                    SELECT 1 FROM dbo.d2_permiso p
                    WHERE p.id_emp = c.id_emp
                      AND p.estado_permiso = 'APROBADO'
                      AND p.fecha_desde::date <= c.fecha::date
                      AND p.fecha_hasta::date >= c.fecha::date
                      AND (p.tipo_horario = 'ENTRADA' OR p.todo_dia = 'SI')
                ))
                OR (c.atraso_lunch > 0 AND NOT EXISTS (
                    SELECT 1 FROM dbo.d2_permiso p
                    WHERE p.id_emp = c.id_emp
                      AND p.estado_permiso = 'APROBADO'
                      AND p.fecha_desde::date <= c.fecha::date
                      AND p.fecha_hasta::date >= c.fecha::date
                      AND (p.tipo_horario = 'ENTRE JORNADA' OR p.todo_dia = 'SI')
                ))
                OR (c.atraso_salida > 0 AND NOT EXISTS (
                    SELECT 1 FROM dbo.d2_permiso p
                    WHERE p.id_emp = c.id_emp
                      AND p.estado_permiso = 'APROBADO'
                      AND p.fecha_desde::date <= c.fecha::date
                      AND p.fecha_hasta::date >= c.fecha::date
                      AND (p.tipo_horario = 'SALIDA' OR p.todo_dia = 'SI')
                ))
              )
            GROUP BY EXTRACT(MONTH FROM c.fecha)::int
        ", [$emp->id_emp, $anio]);

        $porMes = collect($resultado)->keyBy('mes');

        $datos = [];
        for ($m = 1; $m <= $nMes; $m++) {
            $datos[] = (int) ($porMes->get($m)?->dias ?? 0);
        }

        return response()->json([
            'meses' => array_slice($meses, 0, $nMes),
            'datos' => $datos,
        ]);
    }
}
