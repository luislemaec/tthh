<?php
namespace App\Http\Controllers;

use App\Models\Vacacion;
use App\Models\CabeceraVacacion;
use App\Models\DetalleVacacion;
use App\Models\Empleado;
use App\Models\ModalidadLaboral;
use App\Models\Supervisor;
use App\Services\AuditoriaService;
use App\Services\SaldoVacacionesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VacacionesController extends Controller
{
    public function __construct(private SaldoVacacionesService $saldoService)
    {
    }

    private function esSupervisor($id_emp)
    {
        return Supervisor::where("id_supervisor", $id_emp)->exists();
    }

    // Mismo patrón que PermisosController/HorasExtrasController::horaTurnoDelDia() —
    // resuelve el turno real del empleado ese día vía d2_programacion (columna s{día} → id_turno)
    // → d2_turno (concepto ENTRADA/SALIDA). Si no hay turno configurado, cae a un default fijo.
    private function horaTurnoDelDia(string $id_emp, Carbon $fecha, string $concepto): ?string
    {
        $colTurno = 's' . (int) $fecha->format('j');

        $prog = DB::table('dbo.d2_programacion')
            ->where('id_emp', $id_emp)
            ->whereYear('fecha', $fecha->year)
            ->whereMonth('fecha', $fecha->month)
            ->first();

        $idTurno = $prog ? ((int) ($prog->$colTurno ?? 1)) : 1;

        $turno = DB::table('dbo.d2_turno')
            ->where('id_turno', $idTurno)
            ->where('concepto', $concepto)
            ->first();

        return $turno ? Carbon::parse($turno->hora)->format('H:i') : null;
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
        $emp = $request->user();

        if (strtoupper($emp->estado) !== "ACTIVO") {
            return response()->json([
                "cabecera"        => null,
                "detalle"         => [],
                "saldo_calculado" => ["saldo_inicial" => 0, "acumulado_a_hoy" => 0, "tomados" => 0, "dias_disponibles" => 0],
                "inactivo"        => true,
            ]);
        }

        $cabecera = CabeceraVacacion::where("id_emp", $emp->id_emp)->first();
        $detalle  = DetalleVacacion::where("id_emp", $emp->id_emp)
            ->orderBy("numero_periodo", "desc")
            ->get();

        $saldoCalculado = $cabecera
            ? $this->saldoService->calcular($emp, $cabecera)
            : ["saldo_inicial" => 0, "acumulado_a_hoy" => 0, "tomados" => 0, "dias_disponibles" => 0];

        return response()->json([
            "cabecera"          => $cabecera,
            "detalle"           => $detalle,
            "saldo_calculado"   => $saldoCalculado,
            "modalidad_laboral" => $emp->modalidad_laboral,
            "es_nombramiento_definitivo" => ModalidadLaboral::esNombramientoDefinitivo($emp->modalidad_laboral),
        ]);
    }

    // Listar vacaciones
    public function index(Request $request)
    {
        $emp          = $request->user();
        $esAdminOTH   = $this->esAdminOTH($emp->id_emp);
        $esSupervisor = $this->esSupervisor($emp->id_emp);

        $query = Vacacion::with(["empleado.departamento", "aprobador", "informador", "modificadoPor"])
            ->orderBy("fecha_hora", "desc");

        $vista = $request->query("vista", ""); // "mia" | "equipo" | ""

        if ($esAdminOTH) {
            if ($vista === "mia") {
                $query->where("id_emp", $emp->id_emp);
            }
            // vista=equipo o sin vista: ve todos
        } elseif ($esSupervisor) {
            $empleados = $this->empleadosDeSupervisor($emp->id_emp);
            if ($vista === "mia") {
                $query->where("id_emp", $emp->id_emp);
            } elseif ($vista === "equipo") {
                $query->whereIn("id_emp", $empleados);
            } else {
                $query->where(function ($q) use ($emp, $empleados) {
                    $q->where("id_emp", $emp->id_emp)
                      ->orWhereIn("id_emp", $empleados);
                });
            }
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

        if (strtoupper($emp->estado) !== "ACTIVO") {
            return response()->json(["message" => "Solo empleados activos pueden solicitar vacaciones"], 403);
        }

        $diasSolicitados = Carbon::parse($request->fecha_inicial)
            ->diffInDays(Carbon::parse($request->fecha_final)) + 1;

        // Verificar saldo disponible
        $cabecera = CabeceraVacacion::where("id_emp", $emp->id_emp)->first();
        $saldo    = $cabecera ? $this->saldoService->calcular($emp, $cabecera) : null;

        // Bug fix: descontar días de solicitudes PENDIENTE para evitar doble-aprobación simultánea
        $diasPendientes = (int) DB::table('dbo.d2_vacacion')
            ->where('id_emp', $emp->id_emp)
            ->where('estado_permiso', 'PENDIENTE')
            ->selectRaw("COALESCE(SUM(fecha_final::date - fecha_inicial::date + 1), 0) as total")
            ->value('total');

        $saldoReal     = $saldo ? ($saldo['dias_disponibles_real'] ?? 0) : 0;
        $saldoEfectivo = $saldoReal - $diasPendientes;

        $esNombramiento = ModalidadLaboral::esNombramientoDefinitivo($emp->modalidad_laboral);

        if ($saldoEfectivo < $diasSolicitados) {
            if ($esNombramiento) {
                // Nombramiento Definitivo puede solicitar con saldo insuficiente — requiere informe TH
                $requiereInforme = true;
            } else {
                $disponiblesDisplay = max(0, $saldoEfectivo);
                return response()->json([
                    "message" => "No tienes suficientes días disponibles. Disponibles: {$disponiblesDisplay}, solicitados: {$diasSolicitados}"
                ], 422);
            }
        } else {
            $requiereInforme = false;
        }

        // Verificar que no tenga vacaciones en las mismas fechas — condición de solapamiento
        // de intervalos completa (fix 2026-09-29): la versión anterior (whereBetween de
        // fecha_inicial/fecha_final del registro existente contra el rango nuevo) solo
        // detectaba 2 de los 4 casos de solapamiento — se le escapaba justo el más común
        // en la práctica: una solicitud nueva completamente CONTENIDA dentro de un período
        // ya APROBADO (ej. aprobado 30-sep al 14-oct, nueva solicitud 5-oct al 9-oct — ni
        // el 30-sep ni el 14-oct caen dentro del 5-9 oct, así que nunca coincidía). Esto
        // permitía dos solicitudes APROBADO superpuestas y el consecuente doble descuento
        // de saldo sobre los mismos días. La condición correcta (cubre los 4 casos: solapa
        // por la izquierda, por la derecha, la nueva contenida en la existente, o viceversa)
        // es una sola comparación de intervalos, sin OR.
        $existe = Vacacion::where("id_emp", $emp->id_emp)
            ->whereNotIn("estado_permiso", ["NEGADO", "ELIMINADO"])
            ->where("fecha_inicial", "<=", $request->fecha_final)
            ->where("fecha_final", ">=", $request->fecha_inicial)
            ->exists();

        if ($existe) {
            return response()->json(["message" => "Ya tienes vacaciones registradas en esas fechas"], 422);
        }

        // Hora real del turno del empleado (ENTRADA del primer día / SALIDA del último), no la
        // hora fija 08:00-17:00 que mandaba el formulario — mismo criterio ya aplicado en Permisos.
        $horaEntrada = $this->horaTurnoDelDia($emp->id_emp, Carbon::parse($request->fecha_inicial), 'ENTRADA') ?? '08:00';
        $horaSalida  = $this->horaTurnoDelDia($emp->id_emp, Carbon::parse($request->fecha_final), 'SALIDA') ?? '17:00';

        $vacacion = Vacacion::create([
            "id_emp"          => $emp->id_emp,
            "fecha_hora"      => now(),
            "nombre_emp"      => trim($emp->apellido_emp) . " " . trim($emp->nombre_emp),
            "fecha_inicial"   => $request->fecha_inicial,
            "fecha_final"     => $request->fecha_final,
            "hora_desde"      => $request->fecha_inicial . " " . $horaEntrada . ":00",
            "hora_hasta"      => $request->fecha_final   . " " . $horaSalida . ":00",
            "observaciones"   => $request->observaciones,
            "todo_dia"        => $request->todo_dia ?? "SI",
            "estado_permiso"  => "PENDIENTE",
            "ip"              => $request->ip(),
            "requiere_informe"=> $requiereInforme,
        ]);

        if ($requiereInforme) {
            AuditoriaService::log('dbo.d2_vacacion', $vacacion->secuencial_clave, 'SOLICITUD_CON_EXCESO',
                null,
                ['id_emp' => $emp->id_emp, 'dias_solicitados' => $diasSolicitados, 'saldo_efectivo' => $saldoEfectivo],
                $request, "Solicitud de vacaciones con exceso de saldo (requiere informe TH): {$vacacion->nombre_emp}");
        }

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

        if ($vacacion->requiere_informe && $vacacion->informe_estado !== 'FAVORABLE') {
            return response()->json(["message" => "Esta solicitud requiere informe favorable de Talento Humano antes de ser aprobada"], 422);
        }

        $vacacion->update([
            "estado_permiso" => "APROBADO",
            "aprobado_en"    => now(),
            "aprobado_por"   => $supervisor->id_emp,
            "backup_id"      => $request->backup_id     ?? null,
            "backup_nombre"  => $request->backup_nombre ?? null,
            "updated_at"     => now(),
            "updated_by"     => $supervisor->id_emp,
        ]);

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

        AuditoriaService::log('dbo.d2_vacacion', $vacacion->getKey(), 'APROBAR',
            ['estado_permiso' => 'PENDIENTE'],
            ['estado_permiso' => 'APROBADO', 'fecha_inicial' => $vacacion->fecha_inicial, 'fecha_final' => $vacacion->fecha_final, 'dias' => $dias],
            $request, "Aprobación de vacación: {$vacacion->nombre_emp}");

        return response()->json(["message" => "Vacación aprobada correctamente", "vacacion" => $vacacion->load("empleado")]);
    }

    // Empleados del mismo departamento (para seleccionar backup al aprobar)
    public function empleadosDepto(Request $request, $id)
    {
        $vacacion = Vacacion::findOrFail($id);
        $idDepto  = DB::table('dbo.ad_empleado')->where('id_emp', $vacacion->id_emp)->value('id_depto');

        $empleados = DB::table('dbo.ad_empleado')
            ->where('estado', 'ACTIVO')
            ->where('id_depto', $idDepto)
            ->where('id_emp', '!=', $vacacion->id_emp)
            ->orderBy('apellido_emp')
            ->get(['id_emp', 'nombre_emp', 'apellido_emp']);

        return response()->json($empleados);
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
            "updated_at"           => now(),
            "updated_by"           => $supervisor->id_emp,
        ]);

        AuditoriaService::log('dbo.d2_vacacion', $vacacion->getKey(), 'NEGAR',
            ['estado_permiso' => 'PENDIENTE'],
            ['estado_permiso' => 'NEGADO', 'observacion' => $request->observacion_negacion],
            $request, "Negación de vacación: {$vacacion->nombre_emp}");

        return response()->json(["message" => "Vacación negada", "vacacion" => $vacacion->load("empleado")]);
    }

    // Marcar informe favorable/desfavorable (solo TH/Admin)
    public function marcarInforme(Request $request, $id)
    {
        $this->requireRole($request, ['ADMINISTRADOR', 'TALENTO HUMANO']);

        $request->validate([
            'informe_estado' => 'required|in:FAVORABLE,DESFAVORABLE',
        ]);

        $vacacion = Vacacion::findOrFail($id);

        if (!$vacacion->requiere_informe) {
            return response()->json(['message' => 'Esta solicitud no requiere informe'], 422);
        }

        if ($vacacion->estado_permiso !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se puede marcar informe en solicitudes PENDIENTE'], 422);
        }

        $anterior = ['informe_estado' => $vacacion->informe_estado];

        $updates = [
            'informe_estado' => $request->informe_estado,
            'informe_fecha'  => today()->toDateString(),
            'informe_por'    => $request->user()->id_emp,
        ];

        // Informe desfavorable → negar automáticamente la solicitud
        if ($request->informe_estado === 'DESFAVORABLE') {
            $updates['estado_permiso'] = 'NEGADO';
        }

        $vacacion->update($updates);

        $accion = $request->informe_estado === 'FAVORABLE' ? 'INFORME_FAVORABLE' : 'INFORME_DESFAVORABLE';
        AuditoriaService::log('dbo.d2_vacacion', $vacacion->secuencial_clave, $accion,
            $anterior,
            ['informe_estado' => $request->informe_estado, 'informe_por' => $request->user()->id_emp, 'empleado' => $vacacion->nombre_emp],
            $request, "{$accion}: {$vacacion->nombre_emp}");

        return response()->json([
            'message'  => 'Informe registrado correctamente',
            'vacacion' => $vacacion->load('empleado'),
        ]);
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
            "updated_at"           => now(),
            "updated_by"           => $supervisor->id_emp,
        ]);

        AuditoriaService::log('dbo.d2_vacacion', $vacacion->getKey(), 'ELIMINAR',
            ['estado_permiso' => 'PENDIENTE', 'fecha_inicial' => $vacacion->fecha_inicial, 'fecha_final' => $vacacion->fecha_final],
            ['estado_permiso' => 'ELIMINADO', 'observacion' => $request->observacion_negacion],
            $request, "Eliminación de vacación: {$vacacion->nombre_emp}");

        return response()->json(["message" => "Vacación eliminada correctamente"]);
    }

    // Anular vacación ya APROBADA (solo TH/ADMIN) — revierte el descuento del saldo.
    // Mismo patrón que PermisosController::anular(). Uso: la vacación se aprobó pero
    // el empleado no la tomó (cambio de planes, se le necesitó, etc.) y hay que
    // devolverle los días sin dejar el saldo descontado permanentemente.
    public function anular(Request $request, $id)
    {
        $this->requireRole($request, ['ADMINISTRADOR', 'TALENTO HUMANO']);

        $request->validate([
            "observacion_negacion" => "required|string|max:120",
        ]);

        $actor    = $request->user();
        $vacacion = Vacacion::findOrFail($id);

        if ($vacacion->estado_permiso !== "APROBADO") {
            return response()->json(["message" => "Solo se pueden anular vacaciones en estado APROBADO"], 422);
        }

        $dias     = Carbon::parse($vacacion->fecha_inicial)
            ->diffInDays(Carbon::parse($vacacion->fecha_final)) + 1;
        $cabecera = CabeceraVacacion::where("id_emp", $vacacion->id_emp)->first();
        if ($cabecera) {
            $cabecera->dias_x_tomar_normal = (float)($cabecera->dias_x_tomar_normal ?? 0) + $dias;
            $cabecera->total_dias_tomados  = max(0, (float)($cabecera->total_dias_tomados ?? 0) - $dias);
            $cabecera->total_tomados       = max(0, (float)($cabecera->total_tomados      ?? 0) - $dias);
            $cabecera->save();
        }

        $vacacion->update([
            "estado_permiso"       => "ANULADO",
            "observacion_negacion" => $request->observacion_negacion,
            "updated_at"           => now(),
            "updated_by"           => $actor->id_emp,
        ]);

        AuditoriaService::log('dbo.d2_vacacion', $vacacion->getKey(), 'ANULAR',
            ['estado_permiso' => 'APROBADO', 'fecha_inicial' => $vacacion->fecha_inicial, 'fecha_final' => $vacacion->fecha_final, 'dias' => $dias],
            ['estado_permiso' => 'ANULADO', 'observacion' => $request->observacion_negacion],
            $request, "Anulación de vacación aprobada: {$vacacion->nombre_emp}");

        return response()->json([
            "message"  => "Vacación anulada y saldo revertido correctamente",
            "vacacion" => $vacacion->load("empleado"),
        ]);
    }
}
