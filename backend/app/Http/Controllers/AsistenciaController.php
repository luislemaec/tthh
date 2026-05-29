<?php
namespace App\Http\Controllers;

use App\Models\SgControlPersona;
use App\Models\Empleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsistenciaController extends Controller
{
    // Obtener estado actual del empleado autenticado
    public function miEstado(Request $request)
    {
        $emp  = $request->user();
        $hoy  = now()->toDateString();

        $marcaciones = SgControlPersona::where("nro_documento", $emp->id_emp)
            ->whereDate("fecha_hora", $hoy)
            ->orderBy("fecha_hora")
            ->get(["secuencial","clasificacion","concepto","fecha_hora","tipo_marcacion"]);

        $conceptos = $marcaciones->pluck("concepto")->toArray();

        // Determinar siguiente acción
        $siguiente = null;
        if (!in_array("ENTRADA", $conceptos)) {
            $siguiente = "ENTRADA";
        } elseif (!in_array("SALIDA AL LUNCH", $conceptos)) {
            $siguiente = "SALIDA AL LUNCH";
        } elseif (!in_array("ENTRADA DEL LUNCH", $conceptos)) {
            $siguiente = "ENTRADA DEL LUNCH";
        } elseif (!in_array("SALIDA", $conceptos)) {
            $siguiente = "SALIDA";
        }

        $articuloAtrasos = DB::table('dbo.d2_configuracion')
            ->whereRaw("LOWER(concepto) = 'articulo_atrasos'")
            ->value('valor');

        return response()->json([
            "empleado"    => [
                "id_emp"     => $emp->id_emp,
                "nombre"     => $emp->nombre_emp,
                "apellido"   => $emp->apellido_emp,
                "departamento" => $emp->departamento?->nombre_depto,
            ],
            "fecha"           => now()->toDateString(),
            "hora"            => now()->format("H:i:s"),
            "marcaciones"     => $marcaciones,
            "siguiente"       => $siguiente,
            "articulo_atrasos"=> $articuloAtrasos,
        ]);
    }

    // Registrar marcación
    public function marcar(Request $request)
    {
        $request->validate([
            "concepto" => "required|in:ENTRADA,SALIDA AL LUNCH,ENTRADA DEL LUNCH,SALIDA",
            "motivo"   => "nullable|string|max:120",
        ]);

        $emp  = $request->user();
        $hoy  = now()->toDateString();
        $concepto = $request->concepto;

        // Validar modalidad de marcación
        $modalidad = $emp->modalidad_marcacion ?? 'PRESENCIAL';

        if ($modalidad === 'PRESENCIAL') {
            $vlansConf = DB::table('dbo.d2_configuracion')
                ->whereRaw("LOWER(concepto) = 'vlans_permitidas'")
                ->value('valor');

            $vlans    = array_filter(array_map('trim', explode(',', $vlansConf ?? '')));
            $ip       = $request->ip();
            $permitida = empty($vlans) || collect($vlans)->contains(fn($v) => str_starts_with($ip, $v));

            if (!$permitida) {
                return response()->json([
                    'message' => 'Solo puede registrar asistencia desde las instalaciones de la institución.',
                ], 403);
            }
        }

        // Validar que la IP no haya sido usada por otro empleado hoy (si está habilitado)
        $controlIp = DB::table('dbo.d2_configuracion')
            ->whereRaw("LOWER(concepto) = 'control_ip_marcacion'")
            ->value('valor');

        if (trim($controlIp ?? '0') === '1') {
            $ip = $request->ip();
            $ipUsada = SgControlPersona::whereDate('fecha_hora', $hoy)
                ->where('ip', $ip)
                ->where('nro_documento', '!=', $emp->id_emp)
                ->exists();

            if ($ipUsada) {
                return response()->json([
                    'message' => 'Esta computadora ya fue utilizada por otro empleado hoy.',
                ], 403);
            }
        }

        // Validar que no haya marcado el mismo concepto hoy
        $yaMarcado = SgControlPersona::where("nro_documento", $emp->id_emp)
            ->whereDate("fecha_hora", $hoy)
            ->where("concepto", $concepto)
            ->exists();

        if ($yaMarcado) {
            return response()->json([
                "message" => "Ya registraste $concepto hoy"
            ], 422);
        }

        // Validar orden correcto de marcaciones
        $marcaciones = SgControlPersona::where("nro_documento", $emp->id_emp)
            ->whereDate("fecha_hora", $hoy)
            ->pluck("concepto")
            ->toArray();

        $orden = ["ENTRADA", "SALIDA AL LUNCH", "ENTRADA DEL LUNCH", "SALIDA"];
        $idxActual = array_search($concepto, $orden);

        if ($idxActual > 0) {
            $conceptoAnterior = $orden[$idxActual - 1];
            if (!in_array($conceptoAnterior, $marcaciones)) {
                return response()->json([
                    "message" => "Debes registrar $conceptoAnterior primero"
                ], 422);
            }
        }

        // Registrar marcación
        $marcacion = SgControlPersona::create([
            "identificador"  => 0,
            "clasificacion"  => $concepto === "ENTRADA" || $concepto === "ENTRADA DEL LUNCH"
                                ? "ENTRADA" : "SALIDA",
            "nro_documento"  => $emp->id_emp,
            "lugar"          => "WEB",
            "fecha_hora"     => now(),
            "concepto"       => $concepto,
            "motivo"         => $request->motivo ?? null,
            "tipo_marcacion" => $modalidad === 'TELETRABAJO' ? 'TELETRABAJO' : 'WEB',
            "ip"             => $request->ip(),
            "ubicacion"      => $emp->ubicacion ?? "Quito",
            "procesado"      => "NO",
            "origen"         => "WEB",
        ]);

        return response()->json([
            "message"   => "$concepto registrado correctamente",
            "marcacion" => $marcacion,
            "hora"      => now()->format("H:i:s"),
        ], 201);
    }

    // Listar marcaciones del día para administrador
    public function listado(Request $request)
    {
        $fecha = $request->get("fecha", now()->toDateString());
        $depto = $request->get("departamento_id");
        $buscar = $request->get("buscar");

        $query = SgControlPersona::with("empleado.departamento")
            ->whereDate("fecha_hora", $fecha)
            ->orderBy("fecha_hora");

        if ($depto) {
            $query->whereHas("empleado", function($q) use ($depto) {
                $q->where("id_depto", $depto);
            });
        }

        if ($buscar) {
            $query->whereHas("empleado", function($q) use ($buscar) {
                $q->where("nombre_emp",    "ilike", "%$buscar%")
                  ->orWhere("apellido_emp", "ilike", "%$buscar%")
                  ->orWhere("identificacion","ilike", "%$buscar%");
            });
        }

        return response()->json($query->get());
    }

    // Reporte de asistencia por empleado y rango de fechas
    public function reporte(Request $request)
    {
        $request->validate([
            "fecha_desde" => "required|date",
            "fecha_hasta" => "required|date",
        ]);

        $desde  = $request->fecha_desde;
        $hasta  = $request->fecha_hasta;
        $id_emp = $request->id_emp;

        $query = SgControlPersona::with("empleado.departamento")
            ->whereBetween(DB::raw("DATE(fecha_hora)"), [$desde, $hasta])
            ->orderBy("nro_documento")
            ->orderBy("fecha_hora");

        if ($id_emp) {
            $query->where("nro_documento", $id_emp);
        }

        return response()->json($query->get());
    }

    // Historial personal del empleado autenticado
    public function miReporte(Request $request)
    {
        $emp = $request->user();

        $desde = $request->get("fecha_desde", now()->startOfMonth()->toDateString());
        $hasta = $request->get("fecha_hasta", now()->toDateString());
        $tipo  = $request->get("tipo", "todos"); // todos | justificados | injustificados

        // Marcaciones individuales del empleado en el rango
        $marcaciones = DB::table("dbo.sg_control_persona")
            ->where("nro_documento", $emp->id_emp)
            ->whereBetween(DB::raw("DATE(fecha_hora)"), [$desde, $hasta])
            ->orderBy("fecha_hora")
            ->get(["concepto", "fecha_hora"]);

        // Cuadres en el rango (para atrasos)
        $cuadres = DB::table("dbo.d2_cuadre_marcacion")
            ->where("id_emp", $emp->id_emp)
            ->whereBetween(DB::raw("DATE(fecha)"), [$desde, $hasta])
            ->get()
            ->keyBy(fn($c) => substr($c->fecha, 0, 10));

        // Minutos de permisos aprobados por día y tipo_horario
        $permisosRaw = DB::table("dbo.d2_permiso")
            ->where("id_emp", $emp->id_emp)
            ->where("estado_permiso", "APROBADO")
            ->whereNotNull("tipo_horario")
            ->whereBetween(DB::raw("DATE(fecha_desde)"), [$desde, $hasta])
            ->selectRaw("DATE(fecha_desde) as dia, tipo_horario, SUM(EXTRACT(EPOCH FROM (hora_hasta::timestamp - hora_desde::timestamp)) / 60) as minutos")
            ->groupByRaw("DATE(fecha_desde), tipo_horario")
            ->get();

        // Indexar: [dia][tipo_horario] => minutos
        $permisos = [];
        foreach ($permisosRaw as $p) {
            $permisos[$p->dia][$p->tipo_horario] = (float)$p->minutos;
        }

        // Mapa concepto → campo cuadre y tipo_horario del permiso
        $conceptoMap = [
            "ENTRADA"           => ["campo" => "atraso_entrada", "tipo" => "ENTRADA"],
            "ENTRADA DEL LUNCH" => ["campo" => "atraso_lunch",   "tipo" => "ENTRE JORNADA"],
            "SALIDA"            => ["campo" => "atraso_salida",  "tipo" => "SALIDA"],
        ];

        $resultado = [];
        foreach ($marcaciones as $m) {
            $dia    = substr($m->fecha_hora, 0, 10);
            $cuadre = $cuadres[$dia] ?? null;
            $map    = $conceptoMap[$m->concepto] ?? null;
            $atraso = ($cuadre && $map) ? (int)($cuadre->{$map["campo"]} ?? 0) : 0;

            // Minutos justificados específicamente para este concepto
            $minJustificados    = $map ? ($permisos[$dia][$map["tipo"]] ?? 0) : 0;
            $tieneJustificacion = $minJustificados > 0;

            // Aplicar filtro
            if ($tipo === "justificados"   && ($atraso <= 0 || !$tieneJustificacion)) continue;
            if ($tipo === "injustificados" && ($atraso <= 0 || $tieneJustificacion))  continue;

            $resultado[] = [
                "fecha"       => $dia,
                "concepto"    => $m->concepto,
                "hora"        => substr($m->fecha_hora, 11, 5),
                "atraso"      => $atraso,
                "justificado" => $atraso > 0
                    ? ($tieneJustificacion
                        ? ($minJustificados >= $atraso ? "TOTAL" : "PARCIAL")
                        : "NO")
                    : null,
            ];
        }

        return response()->json(array_values($resultado));
    }

    public function reporteSinAtrasos(Request $request)
    {
        $tieneAcceso = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $request->user()->id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])
            ->exists();

        if (!$tieneAcceso) {
            return response()->json(['message' => 'Acceso restringido.'], 403);
        }

        $desde = $request->fecha_desde;
        $hasta = $request->fecha_hasta;

        if (!$desde || !$hasta) {
            return response()->json(['message' => 'Se requieren fecha_desde y fecha_hasta.'], 422);
        }

        $empleados = DB::table('dbo.ad_empleado as e')
            ->join('dbo.ad_departamento as d', 'e.id_depto', '=', 'd.id_depto')
            ->where('e.estado', 'ACTIVO')
            ->where('e.id_depto', '!=', 999)
            ->whereExists(function ($q) use ($desde, $hasta) {
                $q->select(DB::raw(1))
                  ->from('dbo.d2_cuadre_marcacion as c')
                  ->whereColumn('c.id_emp', 'e.id_emp')
                  ->whereBetween(DB::raw("DATE(c.fecha)"), [$desde, $hasta]);
            })
            ->whereNotExists(function ($q) use ($desde, $hasta) {
                $q->select(DB::raw(1))
                  ->from('dbo.d2_cuadre_marcacion as c')
                  ->whereColumn('c.id_emp', 'e.id_emp')
                  ->whereBetween(DB::raw("DATE(c.fecha)"), [$desde, $hasta])
                  ->where(function ($q2) {
                      $q2->where('c.atraso_entrada', '>', 0)
                         ->orWhere('c.atraso_lunch',  '>', 0)
                         ->orWhere('c.atraso_salida', '>', 0);
                  });
            })
            ->select('e.id_emp', 'e.apellido_emp', 'e.nombre_emp', 'e.cargo_empleado', 'd.nombre_depto')
            ->orderBy('d.nombre_depto')
            ->orderBy('e.apellido_emp')
            ->get();

        return response()->json($empleados);
    }
}
