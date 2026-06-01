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

    public function atrasosCoordinacion(Request $request)
    {
        $anio        = now()->year;
        $nMes        = now()->month;
        $mesesLabels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        // 1. Presidencia = único dept raíz (padre_id IS NULL o 999, ≠ 999)
        $presidencia = DB::table('dbo.ad_departamento')
            ->where('id_depto', '!=', 999)
            ->where('estado', 'ACTIVO')
            ->where(function ($q) {
                $q->whereNull('padre_id')->orWhere('padre_id', 999);
            })
            ->orderBy('id_depto')
            ->first(['id_depto', 'nombre_depto']);

        if (!$presidencia) {
            return response()->json(['meses' => [], 'unidades' => [], 'hijos' => []]);
        }

        // 2. Coordinaciones = hijos directos de Presidencia
        $coordinaciones = DB::table('dbo.ad_departamento')
            ->where('padre_id', $presidencia->id_depto)
            ->where('estado', 'ACTIVO')
            ->orderBy('id_depto')
            ->get(['id_depto', 'nombre_depto']);

        // 3. Sub-áreas = hijos de las coordinaciones (para drill-down)
        $idsCoords = $coordinaciones->pluck('id_depto');
        $subAreas  = DB::table('dbo.ad_departamento')
            ->whereIn('padre_id', $idsCoords)
            ->where('estado', 'ACTIVO')
            ->orderBy('id_depto')
            ->get(['id_depto', 'nombre_depto', 'padre_id']);

        // 4. Todos los id_depto involucrados
        $todosIds = collect([$presidencia->id_depto])
            ->merge($idsCoords)
            ->merge($subAreas->pluck('id_depto'))
            ->unique()->values();

        // 5. Query de atrasos del año
        $rawAtrasos = DB::table('dbo.d2_cuadre_marcacion as c')
            ->join('dbo.ad_empleado as e', 'e.id_emp', '=', 'c.id_emp')
            ->whereYear('c.fecha', $anio)
            ->where(function ($q) {
                $q->where('c.atraso_entrada', '>', 0)
                  ->orWhere('c.atraso_lunch', '>', 0)
                  ->orWhere('c.atraso_salida', '>', 0);
            })
            ->whereIn('e.id_depto', $todosIds)
            ->selectRaw('EXTRACT(MONTH FROM c.fecha)::int as mes, e.id_depto, COUNT(*) as dias')
            ->groupByRaw('EXTRACT(MONTH FROM c.fecha)::int, e.id_depto')
            ->get();

        $sumarDatos = function (array $ids) use ($rawAtrasos, $nMes) {
            $datos = [];
            for ($m = 1; $m <= $nMes; $m++) {
                $datos[] = (int) $rawAtrasos->whereIn('id_depto', $ids)->where('mes', $m)->sum('dias');
            }
            return $datos;
        };

        $subAreasPorCoord = $subAreas->groupBy('padre_id');

        // 6. Vista principal: Presidencia (solo directos) + cada coordinación (suma con sub-áreas)
        $unidades = collect();

        $unidades->push([
            'id_depto'    => $presidencia->id_depto,
            'nombre'      => $presidencia->nombre_depto,
            'tiene_hijos' => false,
            'datos'       => $sumarDatos([$presidencia->id_depto]),
        ]);

        foreach ($coordinaciones as $coord) {
            $idsGrupo = [$coord->id_depto];
            if ($subAreasPorCoord->has($coord->id_depto)) {
                $idsGrupo = array_merge($idsGrupo, $subAreasPorCoord[$coord->id_depto]->pluck('id_depto')->toArray());
            }
            $unidades->push([
                'id_depto'    => $coord->id_depto,
                'nombre'      => $coord->nombre_depto,
                'tiene_hijos' => $subAreasPorCoord->has($coord->id_depto),
                'datos'       => $sumarDatos($idsGrupo),
            ]);
        }

        // 7. Detalle de sub-áreas por coordinación (drill-down)
        $resultHijos = [];
        foreach ($subAreasPorCoord as $coordId => $grupo) {
            $resultHijos[$coordId] = $grupo->map(fn($s) => [
                'id_depto' => $s->id_depto,
                'nombre'   => $s->nombre_depto,
                'datos'    => $sumarDatos([$s->id_depto]),
            ])->values();
        }

        return response()->json([
            'meses'    => array_slice($mesesLabels, 0, $nMes),
            'unidades' => $unidades->values(),
            'hijos'    => $resultHijos,
        ]);
    }
}
