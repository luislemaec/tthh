<?php
namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Models\Razon;
use App\Models\Empleado;
use App\Models\Supervisor;
use App\Models\CabeceraVacacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermisosController extends Controller
{
    // Verificar si el empleado es supervisor
    private function esSupervisor($id_emp)
    {
        return Supervisor::where("id_supervisor", $id_emp)->exists();
    }

    // Verificar si el empleado es admin o TH
    private function esAdminOTH($id_emp)
    {
        return DB::table("dbo.admin_usuario_rol as ur")
            ->join("dbo.admin_rol as r", "ur.id_rol", "=", "r.id")
            ->where("ur.id_emp", $id_emp)
            ->whereIn("r.descripcion", ["ADMINISTRADOR", "TALENTO HUMANO"])
            ->exists();
    }

    // Obtener IDs de empleados que supervisa (incluyendo supervisores de depts hijos)
    private function empleadosDeSupervisor($id_supervisor)
    {
        $deptos = Supervisor::where("id_supervisor", $id_supervisor)
            ->pluck("id_depto");

        // Empleados directos en los departamentos supervisados
        $empleadosDirectos = Empleado::whereIn("id_depto", $deptos)
            ->where("estado", "ACTIVO")
            ->where("id_emp", "!=", $id_supervisor)
            ->pluck("id_emp");

        // Supervisores de departamentos hijos de los supervisados
        // (ej: coordinador ve al director, presidencia ve al coordinador)
        $deptosHijos = DB::table("dbo.ad_departamento")
            ->whereIn("padre_id", $deptos)
            ->pluck("id_depto");

        $supervisoresHijos = Supervisor::whereIn("id_depto", $deptosHijos)
            ->where("id_supervisor", "!=", $id_supervisor)
            ->pluck("id_supervisor");

        return $empleadosDirectos->merge($supervisoresHijos)->unique()->values();
    }

    // Listar permisos
    public function index(Request $request)
    {
        $emp         = $request->user();
        $esAdminOTH  = $this->esAdminOTH($emp->id_emp);
        $esSupervisor = $this->esSupervisor($emp->id_emp);

        $query = Permiso::with(["empleado.departamento", "razonPermiso"])
            ->orderBy("fecha_hora", "desc");

        $vista = $request->query("vista", ""); // "mia" | "equipo" | "" (todos)

        if ($esAdminOTH) {
            if ($vista === "mia") {
                $query->where("id_emp", $emp->id_emp);
            }
            // vista=equipo o sin vista: ve todos (admin/TH)
        } elseif ($esSupervisor) {
            $empleados = $this->empleadosDeSupervisor($emp->id_emp);
            if ($vista === "mia") {
                $query->where("id_emp", $emp->id_emp);
            } elseif ($vista === "equipo") {
                $query->whereIn("id_emp", $empleados);
            } else {
                // Sin vista: comportamiento anterior (propio + equipo)
                $query->where(function($q) use ($emp, $empleados) {
                    $q->where("id_emp", $emp->id_emp)
                      ->orWhereIn("id_emp", $empleados);
                });
            }
        } else {
            // Empleado sin rol especial: solo ve los suyos
            $query->where("id_emp", $emp->id_emp);
        }

        // Filtros
        if ($request->filled("estado")) {
            $query->where("estado_permiso", $request->estado);
        }
        if ($request->filled("fecha_desde")) {
            $query->whereDate("fecha_desde", ">=", $request->fecha_desde);
        }
        if ($request->filled("fecha_hasta")) {
            $query->whereDate("fecha_hasta", "<=", $request->fecha_hasta);
        }
        if ($request->filled("descontable")) {
            $query->where("descontable", $request->descontable);
        }

        return response()->json($query->paginate($request->get("per_page", 15)));
    }

    // Obtener info del usuario actual para el frontend
    public function miRol(Request $request)
    {
        $emp = $request->user();
        return response()->json([
            "es_supervisor" => $this->esSupervisor($emp->id_emp),
            "es_admin_th"   => $this->esAdminOTH($emp->id_emp),
        ]);
    }

    // Solicitar nuevo permiso
    public function store(Request $request)
    {
        $request->validate([
            "sec_permiso"   => "required|integer",
            "fecha_desde"   => "required|date",
            "fecha_hasta"   => "required|date",
            "hora_desde"    => "required|string",
            "hora_hasta"    => "required|string",
            "todo_dia"      => "nullable|string",
            "observaciones" => "nullable|string|max:250",
            "concepto"      => "nullable|string|max:20",
            "tipo_horario"  => "required|in:ENTRADA,ENTRE JORNADA,SALIDA",
        ]);

        $emp   = $request->user();

        // Empleados del depto 999 no pueden solicitar permisos
        if ($emp->id_depto == 999) {
            return response()->json([
                "message" => "El usuario administrador no puede solicitar permisos"
            ], 403);
        }

        if (strtoupper($emp->estado) !== "ACTIVO") {
            return response()->json([
                "message" => "Solo empleados activos pueden solicitar permisos"
            ], 403);
        }

        $razon = Razon::findOrFail($request->sec_permiso);

        // Verificar que no tenga un permiso con fechas/horas que se crucen
        $queryExiste = Permiso::where("id_emp", $emp->id_emp)
            ->whereNotIn("estado_permiso", ["NEGADO", "ELIMINADO"])
            ->where(function($q) use ($request) {
                $q->whereBetween("fecha_desde", [$request->fecha_desde, $request->fecha_hasta])
                  ->orWhereBetween("fecha_hasta", [$request->fecha_desde, $request->fecha_hasta]);
            });

        // Si el nuevo permiso NO es todo el día, solo bloquear si hay cruce de horas
        if ($request->todo_dia !== "SI") {
            $horaDesdeNuevo = $request->fecha_desde . " " . $request->hora_desde . ":00";
            $horaHastaNuevo = $request->fecha_hasta . " " . $request->hora_hasta . ":00";
            $queryExiste->where(function($q) use ($horaDesdeNuevo, $horaHastaNuevo) {
                $q->where("todo_dia", "SI")
                  ->orWhere(function($q2) use ($horaDesdeNuevo, $horaHastaNuevo) {
                      $q2->where("hora_desde", "<", $horaHastaNuevo)
                         ->where("hora_hasta", ">", $horaDesdeNuevo);
                  });
            });
        }

        if ($queryExiste->exists()) {
            return response()->json([
                "message" => "Ya tienes un permiso registrado en ese horario"
            ], 422);
        }

        $permiso = Permiso::create([
            "fecha_hora"     => now(),
            "id_emp"         => $emp->id_emp,
            "razon"          => trim($razon->descripcion),
            "fecha_desde"    => $request->fecha_desde,
            "fecha_hasta"    => $request->fecha_hasta,
            "hora_desde"     => $request->fecha_desde . " " . $request->hora_desde . ":00",
            "hora_hasta"     => $request->fecha_hasta . " " . $request->hora_hasta . ":00",
            "usuario"        => $emp->id_emp,
            "cargo"          => 0,
            "sec_permiso"    => $request->sec_permiso,
            "estado_permiso" => "PENDIENTE",
            "todo_dia"       => $request->todo_dia ?? "NO",
            "concepto"       => $request->concepto ?? "PERMISO",
            "observaciones"  => $request->observaciones,
            "terminal"       => $request->ip(),
            "transmitio"     => "NO",
            "descontable"    => $razon->descontable === "SI" ? "SI" : "NO",
            "tipo_horario"   => $request->tipo_horario,
            "origen"         => "WEB",
            "disminuir_dias" => 0,
            "secuencial"     => 0,
            "principal"      => 0,
        ]);

        return response()->json($permiso->load(["empleado", "razonPermiso"]), 201);
    }

    // Ver un permiso
    public function show($id)
    {
        $permiso = Permiso::with(["empleado.departamento", "razonPermiso"])->findOrFail($id);
        return response()->json($permiso);
    }

    // Aprobar permiso (solo supervisor del empleado)
    public function aprobar(Request $request, $id)
    {
        $permiso     = Permiso::findOrFail($id);
        $supervisor  = $request->user();

        // No puede aprobarse a si mismo
        if ($permiso->id_emp === $supervisor->id_emp) {
            return response()->json([
                "message" => "No puedes aprobar tu propio permiso"
            ], 403);
        }

        // Verificar que es supervisor del empleado
        $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
        if (!$empleados->contains($permiso->id_emp)) {
            return response()->json([
                "message" => "No eres supervisor de este empleado"
            ], 403);
        }

        if ($permiso->estado_permiso !== "PENDIENTE") {
            return response()->json([
                "message" => "El permiso no esta en estado PENDIENTE"
            ], 422);
        }

        $permiso->update([
            "estado_permiso" => "APROBADO",
            "usuario"        => $supervisor->id_emp,
        ]);

        // Calcular días a descontar según jornada del empleado
        $empleado     = Empleado::with("jornada")->find($permiso->id_emp);
        $horasJornada = $empleado?->jornada ? (float) $empleado->jornada->normal : 8.0;

        if ($permiso->todo_dia === "SI") {
            $diasDescuento = Carbon::parse($permiso->fecha_desde)
                ->diffInDays(Carbon::parse($permiso->fecha_hasta)) + 1;
        } else {
            $horas         = Carbon::parse($permiso->hora_desde)
                ->diffInMinutes(Carbon::parse($permiso->hora_hasta)) / 60;
            $diasDescuento = round($horas / $horasJornada, 4);
        }

        // Si es descontable → reducir saldo de vacaciones
        if ($permiso->descontable === "SI") {
            $cabecera = CabeceraVacacion::where("id_emp", $permiso->id_emp)->first();
            if ($cabecera) {
                $cabecera->dias_x_tomar_normal = max(0, (float)($cabecera->dias_x_tomar_normal ?? 0) - $diasDescuento);
                $cabecera->total_dias_tomados  = round((float)($cabecera->total_dias_tomados  ?? 0) + $diasDescuento, 4);
                $cabecera->save();
            }
        }

        // Actualizar d2_cuadre_marcacion por cada día del permiso
        $campo       = $permiso->descontable === "SI" ? "horas_decto" : "horaspermiso_pag";
        $diasRango   = $permiso->todo_dia === "SI" ? $diasDescuento : 1;
        $diasXDia    = $permiso->todo_dia === "SI" ? 1 : $diasDescuento;
        $fechaActual = Carbon::parse($permiso->fecha_desde);

        for ($i = 0; $i < $diasRango; $i++) {
            DB::table("dbo.d2_cuadre_marcacion")
                ->where("id_emp", $permiso->id_emp)
                ->whereDate("fecha", $fechaActual->toDateString())
                ->update([
                    $campo => DB::raw("COALESCE($campo, 0) + $diasXDia"),
                ]);
            $fechaActual->addDay();
        }

        return response()->json([
            "message" => "Permiso aprobado correctamente",
            "permiso" => $permiso->load(["empleado", "razonPermiso"]),
        ]);
    }

    // Negar permiso (solo supervisor del empleado)
    public function negar(Request $request, $id)
    {
        $request->validate([
            "observacion_negacion" => "nullable|string|max:120",
        ]);

        $permiso    = Permiso::findOrFail($id);
        $supervisor = $request->user();

        // No puede negarse a si mismo
        if ($permiso->id_emp === $supervisor->id_emp) {
            return response()->json([
                "message" => "No puedes negar tu propio permiso"
            ], 403);
        }

        // Verificar que es supervisor del empleado
        $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
        if (!$empleados->contains($permiso->id_emp)) {
            return response()->json([
                "message" => "No eres supervisor de este empleado"
            ], 403);
        }

        if ($permiso->estado_permiso !== "PENDIENTE") {
            return response()->json([
                "message" => "El permiso no esta en estado PENDIENTE"
            ], 422);
        }

        $permiso->update([
            "estado_permiso"       => "NEGADO",
            "usuario"              => $supervisor->id_emp,
            "observacion_negacion" => $request->observacion_negacion,
        ]);

        return response()->json([
            "message" => "Permiso negado",
            "permiso" => $permiso->load(["empleado", "razonPermiso"]),
        ]);
    }

    // Eliminar permiso (solo supervisor del empleado)
    public function destroy(Request $request, $id)
    {
        $request->validate([
            "observacion_negacion" => "required|string|max:120",
        ]);

        $permiso    = Permiso::findOrFail($id);
        $supervisor = $request->user();

        if ($permiso->id_emp === $supervisor->id_emp) {
            return response()->json(["message" => "No puedes eliminar tu propio permiso"], 403);
        }

        $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
        if (!$this->esAdminOTH($supervisor->id_emp) && !$empleados->contains($permiso->id_emp)) {
            return response()->json(["message" => "No eres supervisor de este empleado"], 403);
        }

        if ($permiso->estado_permiso !== "PENDIENTE") {
            return response()->json(["message" => "El permiso no está en estado PENDIENTE"], 422);
        }

        $permiso->update([
            "estado_permiso"       => "ELIMINADO",
            "observacion_negacion" => $request->observacion_negacion,
        ]);

        return response()->json(["message" => "Permiso eliminado correctamente"]);
    }

    // Listar razones
    public function razones()
    {
        return response()->json(Razon::orderBy("descripcion")->get());
    }

// Estadística de permisos por supervisor
public function estadistica(Request $request)
{
    $request->validate([
        'fecha_desde' => 'required|date',
        'fecha_hasta' => 'required|date',
    ]);

    $emp        = $request->user();
    $esAdmin    = $this->esAdminOTH($emp->id_emp);

    $query = DB::table('dbo.d2_permiso as p')
        ->join('dbo.ad_empleado as sup', 'p.usuario', '=', 'sup.id_emp')
        ->join('dbo.ad_empleado as emp', 'p.id_emp', '=', 'emp.id_emp')
        ->whereBetween('p.fecha_desde', [$request->fecha_desde, $request->fecha_hasta])
        ->whereIn('p.estado_permiso', ['APROBADO', 'NEGADO', 'ELIMINADO'])
        ->where('p.terminal', '!=', '0.0.0.0')
        ->where('p.observaciones', '!=', 'MIGRACION');

    // Supervisor solo ve los empleados de su departamento
    if (!$esAdmin) {
        $empleadosPropios = $this->empleadosDeSupervisor($emp->id_emp);
        $query->whereIn('p.id_emp', $empleadosPropios);
    }

    $datos = $query->select(
            'sup.id_emp as id_supervisor',
            DB::raw("sup.apellido_emp || ' ' || sup.nombre_emp as nombre_supervisor"),
            'p.estado_permiso',
            DB::raw('count(*) as total')
        )
        ->groupBy('sup.id_emp', 'sup.apellido_emp', 'sup.nombre_emp', 'p.estado_permiso')
        ->orderBy('sup.apellido_emp')
        ->get();

    // Agrupar por supervisor
    $resumen = [];
    foreach ($datos as $row) {
        $id = $row->id_supervisor;
        if (!isset($resumen[$id])) {
            $resumen[$id] = [
                'id_supervisor'    => $id,
                'nombre_supervisor'=> $row->nombre_supervisor,
                'aprobados'        => 0,
                'negados'          => 0,
                'eliminados'       => 0,
                'total'            => 0,
            ];
        }
        $resumen[$id][$row->estado_permiso === 'APROBADO' ? 'aprobados' :
                      ($row->estado_permiso === 'NEGADO'   ? 'negados' : 'eliminados')] += $row->total;
        $resumen[$id]['total'] += $row->total;
    }

    return response()->json(array_values($resumen));
}

}
