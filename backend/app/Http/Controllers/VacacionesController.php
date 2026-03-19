<?php
namespace App\Http\Controllers;

use App\Models\Vacacion;
use App\Models\CabeceraVacacion;
use App\Models\Configuracion;
use App\Models\DetalleVacacion;
use App\Models\Empleado;
use App\Models\Supervisor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VacacionesController extends Controller
{
    private function calcularSaldoDisponible(Empleado $emp, CabeceraVacacion $cabecera): array
    {
        $tasas = [
            "LOSEP"              => 2.50,
            "CODIGO DEL TRABAJO" => 1.15,
        ];
        $tasa = $tasas[trim($emp->tipo_contrato)] ?? 0;

        $fechaCorteConfig = Configuracion::find("FECHA_CORTE_VACACIONES");
        $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();

        $diasAcumulados = round(Carbon::today()->diffInDays($fechaCorte) / 30 * $tasa, 2);
        $saldoInicial   = (float) ($cabecera->dias_adicionales  ?? 0);
        $tomados        = (float) ($cabecera->total_dias_tomados ?? 0);
        $disponibles    = round($saldoInicial + $diasAcumulados - $tomados, 2);

        return [
            "saldo_inicial"   => $saldoInicial,
            "acumulado_a_hoy" => $diasAcumulados,
            "tomados"         => $tomados,
            "dias_disponibles"=> max(0, $disponibles),
        ];
    }

    private function esSupervisor($id_emp)
    {
        return Supervisor::where("id_supervisor", $id_emp)->exists();
    }

    private function esAdminOTH($id_emp)
    {
        return DB::table("dbo.admin_usuario_rol as ur")
            ->join("dbo.admin_rol as r", "ur.id_rol", "=", "r.id")
            ->where("ur.id_emp", $id_emp)
            ->whereIn("r.descripcion", ["ADMINISTRADOR", "TALENTO HUMANO"])
            ->exists();
    }

    private function empleadosDeSupervisor($id_supervisor)
    {
        $deptos = Supervisor::where("id_supervisor", $id_supervisor)->pluck("id_depto");
        return Empleado::whereIn("id_depto", $deptos)
            ->where("estado", "ACTIVO")
            ->where("id_emp", "!=", $id_supervisor)
            ->pluck("id_emp");
    }

    // Rol del usuario autenticado
    public function miRol(Request $request)
    {
        $emp = $request->user();
        return response()->json([
            "es_supervisor" => $this->esSupervisor($emp->id_emp),
            "es_admin_th"   => $this->esAdminOTH($emp->id_emp),
        ]);
    }

    // Saldo de vacaciones del empleado autenticado
    public function miSaldo(Request $request)
    {
        $emp      = $request->user();
        $cabecera = CabeceraVacacion::where("id_emp", $emp->id_emp)->first();
        $detalle  = DetalleVacacion::where("id_emp", $emp->id_emp)
            ->orderBy("numero_periodo", "desc")
            ->get();

        $saldoCalculado = $cabecera
            ? $this->calcularSaldoDisponible($emp, $cabecera)
            : ["saldo_inicial" => 0, "acumulado_a_hoy" => 0, "tomados" => 0, "dias_disponibles" => 0];

        return response()->json([
            "cabecera"       => $cabecera,
            "detalle"        => $detalle,
            "saldo_calculado"=> $saldoCalculado,
        ]);
    }

    // Listar vacaciones
    public function index(Request $request)
    {
        $emp          = $request->user();
        $esAdminOTH   = $this->esAdminOTH($emp->id_emp);
        $esSupervisor = $this->esSupervisor($emp->id_emp);

        $query = Vacacion::with(["empleado.departamento"])
            ->orderBy("fecha_hora", "desc");

        if ($esAdminOTH) {
            // Admin y TH ven todos
        } elseif ($esSupervisor) {
            $deptos    = Supervisor::where("id_supervisor", $emp->id_emp)->pluck("id_depto");
            $empleados = Empleado::whereIn("id_depto", $deptos)
                ->where("estado", "ACTIVO")
                ->where("id_emp", "!=", $emp->id_emp)
                ->pluck("id_emp");
            $query->where(function ($q) use ($emp, $empleados) {
                $q->where("id_emp", $emp->id_emp)
                  ->orWhereIn("id_emp", $empleados);
            });
        } else {
            $query->where("id_emp", $emp->id_emp);
        }

        if ($request->filled("estado")) {
            $query->where("estado_permiso", $request->estado);
        }
        if ($request->filled("fecha_desde")) {
            $query->whereDate("fecha_inicial", ">=", $request->fecha_desde);
        }
        if ($request->filled("fecha_hasta")) {
            $query->whereDate("fecha_final", "<=", $request->fecha_hasta);
        }

        return response()->json($query->paginate($request->get("per_page", 15)));
    }

    // Solicitar vacaciones
    public function store(Request $request)
    {
        $request->validate([
            "fecha_inicial"  => "required|date",
            "fecha_final"    => "required|date|after_or_equal:fecha_inicial",
            "hora_desde"     => "required|string",
            "hora_hasta"     => "required|string",
            "todo_dia"       => "nullable|string",
            "observaciones"  => "nullable|string|max:250",
        ]);

        $emp = $request->user();

        if ($emp->id_depto == 999) {
            return response()->json(["message" => "El usuario administrador no puede solicitar vacaciones"], 403);
        }

        // Verificar saldo disponible
        $cabecera = CabeceraVacacion::where("id_emp", $emp->id_emp)->first();
        $saldo    = $cabecera ? $this->calcularSaldoDisponible($emp, $cabecera) : null;

        if (!$saldo || $saldo["dias_disponibles"] <= 0) {
            return response()->json(["message" => "No tienes días de vacaciones disponibles"], 422);
        }

        $diasSolicitados = Carbon::parse($request->fecha_inicial)
            ->diffInDays(Carbon::parse($request->fecha_final)) + 1;

        if ($diasSolicitados > $saldo["dias_disponibles"]) {
            return response()->json([
                "message" => "No tienes suficientes días disponibles. Disponibles: {$saldo['dias_disponibles']}, solicitados: {$diasSolicitados}"
            ], 422);
        }

        // Verificar que no tenga vacaciones en las mismas fechas
        $existe = Vacacion::where("id_emp", $emp->id_emp)
            ->whereNotIn("estado_permiso", ["NEGADO", "ELIMINADO"])
            ->where(function ($q) use ($request) {
                $q->whereBetween("fecha_inicial", [$request->fecha_inicial, $request->fecha_final])
                  ->orWhereBetween("fecha_final", [$request->fecha_inicial, $request->fecha_final]);
            })->exists();

        if ($existe) {
            return response()->json(["message" => "Ya tienes vacaciones registradas en esas fechas"], 422);
        }

        $vacacion = Vacacion::create([
            "id_emp"         => $emp->id_emp,
            "fecha_hora"     => now(),
            "nombre_emp"     => trim($emp->apellido_emp) . " " . trim($emp->nombre_emp),
            "fecha_inicial"  => $request->fecha_inicial,
            "fecha_final"    => $request->fecha_final,
            "hora_desde"     => $request->fecha_inicial . " " . $request->hora_desde . ":00",
            "hora_hasta"     => $request->fecha_final   . " " . $request->hora_hasta . ":00",
            "observaciones"  => $request->observaciones,
            "todo_dia"       => $request->todo_dia ?? "SI",
            "estado_permiso" => "PENDIENTE",
            "ip"             => $request->ip(),
        ]);

        return response()->json($vacacion->load("empleado"), 201);
    }

    // Aprobar vacación
    public function aprobar(Request $request, $id)
    {
        $vacacion   = Vacacion::findOrFail($id);
        $supervisor = $request->user();

        if ($vacacion->id_emp === $supervisor->id_emp) {
            return response()->json(["message" => "No puedes aprobar tus propias vacaciones"], 403);
        }

        $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
        if (!$this->esAdminOTH($supervisor->id_emp) && !$empleados->contains($vacacion->id_emp)) {
            return response()->json(["message" => "No eres supervisor de este empleado"], 403);
        }

        if ($vacacion->estado_permiso !== "PENDIENTE") {
            return response()->json(["message" => "La solicitud no está en estado PENDIENTE"], 422);
        }

        $vacacion->update(["estado_permiso" => "APROBADO"]);

        // Descontar días del saldo
        $dias     = Carbon::parse($vacacion->fecha_inicial)
            ->diffInDays(Carbon::parse($vacacion->fecha_final)) + 1;
        $cabecera = CabeceraVacacion::where("id_emp", $vacacion->id_emp)->first();
        if ($cabecera) {
            $cabecera->dias_x_tomar_normal  = max(0, (float)($cabecera->dias_x_tomar_normal  ?? 0) - $dias);
            $cabecera->total_dias_tomados   = (float)($cabecera->total_dias_tomados ?? 0) + $dias;
            $cabecera->total_tomados        = (float)($cabecera->total_tomados      ?? 0) + $dias;
            $cabecera->save();
        }

        return response()->json(["message" => "Vacación aprobada correctamente", "vacacion" => $vacacion->load("empleado")]);
    }

    // Negar vacación
    public function negar(Request $request, $id)
    {
        $request->validate([
            "observacion_negacion" => "nullable|string|max:120",
        ]);

        $vacacion   = Vacacion::findOrFail($id);
        $supervisor = $request->user();

        if ($vacacion->id_emp === $supervisor->id_emp) {
            return response()->json(["message" => "No puedes negar tus propias vacaciones"], 403);
        }

        $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
        if (!$this->esAdminOTH($supervisor->id_emp) && !$empleados->contains($vacacion->id_emp)) {
            return response()->json(["message" => "No eres supervisor de este empleado"], 403);
        }

        if ($vacacion->estado_permiso !== "PENDIENTE") {
            return response()->json(["message" => "La solicitud no está en estado PENDIENTE"], 422);
        }

        $vacacion->update([
            "estado_permiso"       => "NEGADO",
            "observacion_negacion" => $request->observacion_negacion,
        ]);

        return response()->json(["message" => "Vacación negada", "vacacion" => $vacacion->load("empleado")]);
    }

    // Eliminar vacación
    public function destroy(Request $request, $id)
    {
        $request->validate([
            "observacion_negacion" => "required|string|max:120",
        ]);

        $vacacion   = Vacacion::findOrFail($id);
        $supervisor = $request->user();

        if ($vacacion->id_emp === $supervisor->id_emp) {
            return response()->json(["message" => "No puedes eliminar tus propias vacaciones"], 403);
        }

        $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
        if (!$this->esAdminOTH($supervisor->id_emp) && !$empleados->contains($vacacion->id_emp)) {
            return response()->json(["message" => "No eres supervisor de este empleado"], 403);
        }

        if ($vacacion->estado_permiso !== "PENDIENTE") {
            return response()->json(["message" => "La solicitud no está en estado PENDIENTE"], 422);
        }

        $vacacion->update([
            "estado_permiso"       => "ELIMINADO",
            "observacion_negacion" => $request->observacion_negacion,
        ]);

        return response()->json(["message" => "Vacación eliminada correctamente"]);
    }
}
