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

        return response()->json([
            "empleado"    => [
                "id_emp"     => $emp->id_emp,
                "nombre"     => $emp->nombre_emp,
                "apellido"   => $emp->apellido_emp,
                "departamento" => $emp->departamento?->nombre_depto,
            ],
            "fecha"       => now()->toDateString(),
            "hora"        => now()->format("H:i:s"),
            "marcaciones" => $marcaciones,
            "siguiente"   => $siguiente,
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
            "tipo_marcacion" => "WEB",
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
        $tipo  = $request->get("tipo", "todos"); // todos | atrasos

        // Días con marcaciones reales agrupados por fecha
        $dias = DB::table("dbo.sg_control_persona as s")
            ->where("s.nro_documento", $emp->id_emp)
            ->whereBetween(DB::raw("DATE(s.fecha_hora)"), [$desde, $hasta])
            ->selectRaw("DATE(s.fecha_hora) as dia")
            ->groupByRaw("DATE(s.fecha_hora)")
            ->pluck("dia");

        // Para cada día obtenemos marcaciones + cuadre si existe
        $resultado = [];
        foreach ($dias as $dia) {
            $marcaciones = DB::table("dbo.sg_control_persona")
                ->where("nro_documento", $emp->id_emp)
                ->whereDate("fecha_hora", $dia)
                ->orderBy("fecha_hora")
                ->get(["concepto", "fecha_hora"]);

            $entrada  = $marcaciones->firstWhere("concepto", "ENTRADA");
            $salLunch = $marcaciones->firstWhere("concepto", "SALIDA AL LUNCH");
            $entLunch = $marcaciones->firstWhere("concepto", "ENTRADA DEL LUNCH");
            $salida   = $marcaciones->firstWhere("concepto", "SALIDA");

            // Cuadre procesado si existe
            $cuadre = DB::table("dbo.d2_cuadre_marcacion")
                ->where("id_emp", $emp->id_emp)
                ->whereDate("fecha", $dia)
                ->first();

            $resultado[] = [
                "fecha"              => $dia,
                "falta"              => "N",
                "hora_real_entrada"  => $entrada  ? substr($entrada->fecha_hora,  11, 5) : null,
                "hora_real_sal_lunch"=> $salLunch ? substr($salLunch->fecha_hora, 11, 5) : null,
                "hora_real_ent_lunch"=> $entLunch ? substr($entLunch->fecha_hora, 11, 5) : null,
                "hora_real_sal"      => $salida   ? substr($salida->fecha_hora,   11, 5) : null,
                "hora_turno_entrada" => $cuadre->hora_turno_entrada  ?? null,
                "hora_turno_sal_lunch"=> $cuadre->hora_turno_sal_lunch ?? null,
                "hora_turno_ent_lunch"=> $cuadre->hora_turno_ent_lunch ?? null,
                "hora_turno_sal"     => $cuadre->hora_turno_sal      ?? null,
                "atraso_entrada"     => $cuadre->atraso_entrada  ?? 0,
                "atraso_lunch"       => $cuadre->atraso_lunch    ?? 0,
                "atraso_salida"      => $cuadre->atraso_salida   ?? 0,
                "horas_totales"      => $cuadre->horas_totales   ?? null,
                "horas_decto"        => $cuadre->horas_decto     ?? 0,
            ];
        }

        if ($tipo === "atrasos") {
            $resultado = array_values(array_filter($resultado, function ($r) {
                return $r["atraso_entrada"] > 0
                    || $r["atraso_lunch"]   > 0
                    || $r["atraso_salida"]  > 0
                    || $r["falta"]          === "S";
            }));
        }

        return response()->json($resultado);
    }
}
