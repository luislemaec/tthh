<?php
namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Models\Razon;
use App\Models\Empleado;
use App\Models\Supervisor;
use App\Models\CabeceraVacacion;
use App\Services\AuditoriaService;
use App\Services\SaldoVacacionesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Barryvdh\DomPDF\Facade\Pdf;

class PermisosController extends Controller
{
    private string $alfrescoBase;
    private string $alfrescoUser;
    private string $alfrescoPass;
    private string $alfrescoSite;

    public function __construct(private SaldoVacacionesService $saldoService)
    {
        $this->alfrescoBase = config('services.alfresco.base');
        $this->alfrescoUser = config('services.alfresco.user');
        $this->alfrescoPass = config('services.alfresco.pass');
        $this->alfrescoSite = config('services.alfresco.site');
    }

    private function getDocLibNodeId(): string
    {
        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/sites/{$this->alfrescoSite}/containers/documentLibrary");
        if (!$resp->successful()) abort(502, 'No se pudo conectar con Alfresco');
        return $resp->json('entry.id');
    }

    private function getOrCreateFolderNodeId(string $parentNodeId, string $folderName): string
    {
        $buscarPorNombre = function (string $parent, string $nombre): ?string {
            $resp    = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
                ->get("{$this->alfrescoBase}/nodes/{$parent}/children", [
                    'where'    => '(isFolder=true)',
                    'maxItems' => 500,
                ]);
            $entries = $resp->json('list.entries') ?? [];
            foreach ($entries as $e) {
                if ($e['entry']['name'] === $nombre) return $e['entry']['id'];
            }
            return null;
        };

        $found = $buscarPorNombre($parentNodeId, $folderName);
        if ($found) return $found;

        $create = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->post("{$this->alfrescoBase}/nodes/{$parentNodeId}/children", [
                'name'     => $folderName,
                'nodeType' => 'cm:folder',
            ]);

        if ($create->status() === 409) {
            $found = $buscarPorNombre($parentNodeId, $folderName);
            if ($found) return $found;
        }

        if (!$create->successful()) abort(502, 'No se pudo crear la carpeta en Alfresco');
        return $create->json('entry.id');
    }

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

        $query = Permiso::with(["empleado.departamento", "razonPermiso", "aprobador"])
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

        // Export si se solicita — exige rango de fechas para no traer el historial
        // completo de permisos de la institución de una sola vez (mismo criterio que
        // el resto de reportes del sistema, ej. ReportesController).
        if ($request->filled('formato')) {
            if (!$request->filled('fecha_desde') || !$request->filled('fecha_hasta')) {
                return response()->json(['message' => 'Para exportar debes indicar fecha_desde y fecha_hasta.'], 422);
            }
            $items = $query->limit(5000)->get();
            return $this->exportarPermisosArchivo($items, $request->formato, $request);
        }

        $result = $query->paginate($request->get("per_page", 15));

        // Aviso para el supervisor: permiso ENTRADA/SALIDA descontable de un día
        // que ya pasó y no tuvo atraso real registrado (posible error del empleado)
        $hoy = Carbon::today();
        foreach ($result->items() as $permiso) {
            $permiso->sin_atraso = false;
            if (
                $permiso->descontable === "SI" &&
                in_array($permiso->tipo_horario, ["ENTRADA", "SALIDA"]) &&
                $permiso->fecha_desde &&
                Carbon::parse($permiso->fecha_desde)->lte($hoy)
            ) {
                $cuadre = DB::table("dbo.d2_cuadre_marcacion")
                    ->where("id_emp", $permiso->id_emp)
                    ->whereDate("fecha", Carbon::parse($permiso->fecha_desde)->toDateString())
                    ->first();
                if ($cuadre) {
                    $atraso = $permiso->tipo_horario === "ENTRADA" ? $cuadre->atraso_entrada : $cuadre->atraso_salida;
                    $permiso->sin_atraso = (float) $atraso === 0.0;
                }
            }
        }

        return response()->json($result);
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
    // Igual que ProcesarCuadre.php: resuelve el turno asignado a un empleado en una fecha
    // puntual vía d2_programacion (columna s{día} = id_turno), con fallback a turno 1 si no
    // hay programación para ese mes. Devuelve la hora del concepto (ENTRADA/SALIDA) en "H:i",
    // o null si el turno no tiene ese concepto configurado.
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
            "tipo_horario"  => $request->todo_dia === 'SI'
                ? "nullable"
                : "required|in:ENTRADA,ENTRE JORNADA,SALIDA",
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

        // Verificar que no tenga un permiso del mismo tipo con fechas/horas que se crucen.
        // Permisos de distinto tipo_horario (ej. ENTRADA y SALIDA) pueden coexistir el mismo día.
        $queryExiste = Permiso::where("id_emp", $emp->id_emp)
            ->whereNotIn("estado_permiso", ["NEGADO", "ELIMINADO", "ANULADO"])
            ->where("tipo_horario", $request->tipo_horario)
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
                "message" => "Ya tienes un permiso de ese tipo registrado en ese horario"
            ], 422);
        }

        // "Todo el día": la hora de entrada/salida se toma del turno real asignado ese día
        // (d2_programacion → d2_turno), no de lo que traiga el formulario — antes quedaba
        // fijo en 08:00-17:00 aunque el horario real del empleado fuera distinto (ej. 16:30).
        // Con horario partido, usar la ENTRADA del primer día y la SALIDA del último — si el
        // turno no tiene ese concepto configurado, cae al valor que mandó el formulario.
        $horaDesdeFinal = $request->hora_desde;
        $horaHastaFinal = $request->hora_hasta;
        if ($request->todo_dia === "SI") {
            $horaDesdeFinal = $this->horaTurnoDelDia($emp->id_emp, Carbon::parse($request->fecha_desde), 'ENTRADA')
                ?? $request->hora_desde;
            $horaHastaFinal = $this->horaTurnoDelDia($emp->id_emp, Carbon::parse($request->fecha_hasta), 'SALIDA')
                ?? $request->hora_hasta;
        }

        $permiso = Permiso::create([
            "fecha_hora"     => now(),
            "id_emp"         => $emp->id_emp,
            "razon"          => trim($razon->descripcion),
            "fecha_desde"    => $request->fecha_desde,
            "fecha_hasta"    => $request->fecha_hasta,
            "hora_desde"     => $request->fecha_desde . " " . $horaDesdeFinal . ":00",
            "hora_hasta"     => $request->fecha_hasta . " " . $horaHastaFinal . ":00",
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

        AuditoriaService::log('dbo.d2_permiso', $permiso->getKey(), 'SOLICITAR',
            null,
            ['fecha_desde' => $permiso->fecha_desde, 'fecha_hasta' => $permiso->fecha_hasta,
             'hora_desde' => $permiso->hora_desde, 'hora_hasta' => $permiso->hora_hasta,
             'razon' => $permiso->razon, 'descontable' => $permiso->descontable],
            $request, "Solicitud de permiso: {$this->nombreEmpleadoPermiso($permiso)}");

        return response()->json($permiso->load(["empleado", "razonPermiso"]), 201);
    }

    // Ver un permiso — solo el dueño, su supervisor, o TH/Admin
    public function show(Request $request, $id)
    {
        $permiso = Permiso::with(["empleado.departamento", "razonPermiso"])->findOrFail($id);
        if (!$this->puedeVerPermiso($request, $permiso)) {
            return response()->json(["message" => "No tienes acceso a este permiso"], 403);
        }
        return response()->json($permiso);
    }

    // Dueño del permiso, su supervisor, o TH/Admin pueden verlo/descargarlo
    private function puedeVerPermiso(Request $request, Permiso $permiso): bool
    {
        $actor = $request->user();
        if ($permiso->id_emp === $actor->id_emp) return true;
        if ($this->esAdminOTH($actor->id_emp)) return true;
        return $this->empleadosDeSupervisor($actor->id_emp)->contains($permiso->id_emp);
    }

    // Solo el dueño del permiso o TH/Admin pueden subir/eliminar sus documentos de respaldo
    private function puedeEditarDocumentosPermiso(Request $request, Permiso $permiso): bool
    {
        $actor = $request->user();
        return $permiso->id_emp === $actor->id_emp || $this->esAdminOTH($actor->id_emp);
    }

    // Nombre del empleado del permiso, para mensajes de auditoría — d2_permiso NO tiene
    // columna nombre_emp (esa es de ad_empleado), hay que ir por la relación
    private function nombreEmpleadoPermiso(Permiso $permiso): string
    {
        $emp = $permiso->empleado;
        return $emp ? trim(trim($emp->apellido_emp ?? '') . ' ' . trim($emp->nombre_emp ?? '')) : $permiso->id_emp;
    }

    // Calcula el saldo interno de vacaciones SIN aplicar el tope de 60 días.
    // Delegado a SaldoVacacionesService (única fuente de verdad) — se mantiene el
    // nombre/firma para no tocar el único punto que lo llama en aprobar().
    private function calcularInternoVac(Empleado $emp, CabeceraVacacion $cabecera): float
    {
        return $this->saldoService->calcularInterno($emp, $cabecera);
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

        // Verificar que es supervisor del empleado (TH/ADMIN pueden aprobar cualquiera)
        if (!$this->esAdminOTH($supervisor->id_emp)) {
            $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
            if (!$empleados->contains($permiso->id_emp)) {
                return response()->json([
                    "message" => "No eres supervisor de este empleado"
                ], 403);
            }
        }

        if ($permiso->estado_permiso !== "PENDIENTE") {
            return response()->json([
                "message" => "El permiso no esta en estado PENDIENTE"
            ], 422);
        }

        $permiso->update([
            "estado_permiso" => "APROBADO",
            "usuario"        => $supervisor->id_emp,
            "aprobado_en"    => now(),
        ]);

        // Calcular días a descontar según jornada del empleado
        $empleado     = Empleado::with("jornada")->find($permiso->id_emp);
        $horasJornada = $empleado?->jornada ? (float) $empleado->jornada->normal : 8.0;

        // Factor proporcional sábados/domingos: 30 días calendario = 22 hábiles + 8 fin de semana
        // Cada día hábil de permiso carga 1 + 8/22 = 1.3636 días del saldo de vacaciones
        $factorFds = 30 / 22;

        if ($permiso->todo_dia === "SI") {
            $diasBase      = Carbon::parse($permiso->fecha_desde)
                ->diffInDays(Carbon::parse($permiso->fecha_hasta)) + 1;
            $diasDescuento = round($diasBase * $factorFds, 4);
        } else {
            $horas         = Carbon::parse($permiso->hora_desde)
                ->diffInMinutes(Carbon::parse($permiso->hora_hasta)) / 60;
            $diasDescuento = round($horas / $horasJornada * $factorFds, 4);
        }

        // Si es descontable → reducir saldo de vacaciones
        if ($permiso->descontable === "SI") {
            $cabecera = CabeceraVacacion::where("id_emp", $permiso->id_emp)->first();
            if ($cabecera) {
                // Calcular el saldo interno real (sin tope) para saber cuánto excede los 60 días.
                // Si el empleado tiene 70 días internos → exceso = 10 → el permiso primero consume ese
                // exceso invisible y luego el descuento real, de modo que el saldo visible (≤60) baje correctamente.
                $internoSaldo = $this->calcularInternoVac($empleado, $cabecera);
                $exceso       = max(0.0, $internoSaldo - 60.0);
                $efectivo     = round($exceso + $diasDescuento, 4);

                $cabecera->dias_x_tomar_normal = max(0, (float)($cabecera->dias_x_tomar_normal ?? 0) - $diasDescuento);
                $cabecera->total_dias_tomados  = round((float)($cabecera->total_dias_tomados  ?? 0) + $efectivo, 4);
                $cabecera->save();

                // Guardar el monto efectivo para que anular() pueda revertir exactamente lo correcto
                DB::table('dbo.d2_permiso')
                    ->where($permiso->getKeyName(), $permiso->getKey())
                    ->update(['dias_descuento_efectivo' => $efectivo]);
            }
        }

        // NOTA: el aporte de este permiso a d2_cuadre_marcacion (horas_decto/horaspermiso_pag)
        // ya NO se escribe aquí. Se calcula siempre desde cero en ProcesarCuadre (comando
        // procesar:cuadre) a partir de los permisos APROBADO vigentes ese día — así el valor
        // es idempotente: no se pierde si el cuadre de ese día aún no existe (permiso a futuro)
        // ni se sobrescribe si el cuadre se reprocesa después. Si el permiso es para hoy o una
        // fecha ya procesada, correr `php artisan procesar:cuadre --fecha=YYYY-MM-DD` para reflejarlo.

        AuditoriaService::log('dbo.d2_permiso', $permiso->getKey(), 'APROBAR',
            ['estado_permiso' => 'PENDIENTE'],
            ['estado_permiso' => 'APROBADO', 'fecha_desde' => $permiso->fecha_desde, 'fecha_hasta' => $permiso->fecha_hasta,
             'hora_desde' => $permiso->hora_desde, 'hora_hasta' => $permiso->hora_hasta, 'descontable' => $permiso->descontable],
            $request, "Aprobación de permiso: {$this->nombreEmpleadoPermiso($permiso)}");

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

        // Verificar que es supervisor del empleado (TH/ADMIN pueden negar cualquiera)
        if (!$this->esAdminOTH($supervisor->id_emp)) {
            $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
            if (!$empleados->contains($permiso->id_emp)) {
                return response()->json([
                    "message" => "No eres supervisor de este empleado"
                ], 403);
            }
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

        AuditoriaService::log('dbo.d2_permiso', $permiso->getKey(), 'NEGAR',
            ['estado_permiso' => 'PENDIENTE'],
            ['estado_permiso' => 'NEGADO', 'observacion' => $request->observacion_negacion],
            $request, "Negación de permiso: {$this->nombreEmpleadoPermiso($permiso)}");

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

        AuditoriaService::log('dbo.d2_permiso', $permiso->getKey(), 'ELIMINAR',
            ['estado_permiso' => 'PENDIENTE', 'fecha_desde' => $permiso->fecha_desde, 'fecha_hasta' => $permiso->fecha_hasta,
             'hora_desde' => $permiso->hora_desde, 'hora_hasta' => $permiso->hora_hasta],
            ['estado_permiso' => 'ELIMINADO', 'observacion' => $request->observacion_negacion],
            $request, "Eliminación de permiso: {$this->nombreEmpleadoPermiso($permiso)}");

        return response()->json(["message" => "Permiso eliminado correctamente"]);
    }

    // GET /api/permisos/{id}/documentos
    public function listarDocumentos(Request $request, $id)
    {
        $permiso = Permiso::findOrFail($id);
        if (!$this->puedeVerPermiso($request, $permiso)) {
            return response()->json(['message' => 'No tienes acceso a este permiso'], 403);
        }

        $docs = DB::table('dbo.d2_permiso_documento')
            ->where('permiso_id', $id)
            ->orderBy('created_at')
            ->get();
        return response()->json($docs);
    }

    // POST /api/permisos/{id}/documentos
    public function subirDocumento(Request $request, $id)
    {
        $request->validate([
            'archivo'  => 'required|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'tipo_doc' => 'required|string|max:60',
        ]);

        $permiso = Permiso::with('empleado')->findOrFail($id);
        if (!$this->puedeEditarDocumentosPermiso($request, $permiso)) {
            return response()->json(['message' => 'No puedes adjuntar documentos a un permiso de otro empleado'], 403);
        }
        if ($permiso->estado_permiso !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se pueden adjuntar documentos en permisos PENDIENTES.'], 422);
        }
        $empleado     = $permiso->empleado;
        $anio         = Carbon::parse($permiso->fecha_desde)->year;
        $cedula       = $empleado->id_emp;
        $apellido     = strtoupper(trim($empleado->apellido_emp));
        $carpetaEmp   = "{$cedula}_{$apellido}";
        $relativePath = "permisos/{$anio}/{$carpetaEmp}";

        $docLibId   = $this->getDocLibNodeId();
        $archivo    = $request->file('archivo');
        $ext        = $archivo->getClientOriginalExtension();
        $nombreBase = "permiso_{$id}_{$request->tipo_doc}.{$ext}";

        $upload = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach('filedata', file_get_contents($archivo->getRealPath()), $nombreBase)
            ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
                'name'         => $nombreBase,
                'nodeType'     => 'cm:content',
                'relativePath' => $relativePath,
                'autoRename'   => true,
            ]);

        if (!$upload->successful()) {
            return response()->json(['message' => 'Error al subir el archivo a Alfresco'], 502);
        }

        $alfrescoId   = $upload->json('entry.id');
        $nombreFinal  = $upload->json('entry.name');

        DB::table('dbo.d2_permiso_documento')->insert([
            'permiso_id'     => $id,
            'tipo_doc'       => $request->tipo_doc,
            'nombre_archivo' => $nombreFinal,
            'alfresco_id'    => $alfrescoId,
            'created_by'     => $request->user()->id_emp,
            'created_at'     => now(),
        ]);

        return response()->json(['message' => 'Documento subido correctamente.', 'nombre' => $nombreFinal], 201);
    }

    // DELETE /api/permisos/{id}/documentos/{docId}
    public function eliminarDocumento(Request $request, $id, $docId)
    {
        $permiso = Permiso::findOrFail($id);
        if (!$this->puedeEditarDocumentosPermiso($request, $permiso)) {
            return response()->json(['message' => 'No puedes eliminar documentos de un permiso de otro empleado'], 403);
        }
        if ($permiso->estado_permiso !== 'PENDIENTE') {
            return response()->json(['message' => 'No se pueden eliminar documentos de un permiso ya procesado.'], 422);
        }

        $doc = DB::table('dbo.d2_permiso_documento')
            ->where('id', $docId)
            ->where('permiso_id', $id)
            ->first();

        if (!$doc) return response()->json(['message' => 'Documento no encontrado'], 404);

        Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->delete("{$this->alfrescoBase}/nodes/{$doc->alfresco_id}");

        DB::table('dbo.d2_permiso_documento')->where('id', $docId)->delete();

        return response()->json(['message' => 'Documento eliminado']);
    }

    // GET /api/permisos/{id}/documentos/{docId}/descargar
    public function descargarDocumento(Request $request, $id, $docId)
    {
        $permiso = Permiso::findOrFail($id);
        if (!$this->puedeVerPermiso($request, $permiso)) {
            return response()->json(['message' => 'No tienes acceso a este permiso'], 403);
        }

        $doc = DB::table('dbo.d2_permiso_documento')
            ->where('id', $docId)
            ->where('permiso_id', $id)
            ->first();

        if (!$doc) return response()->json(['message' => 'Documento no encontrado'], 404);

        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/nodes/{$doc->alfresco_id}/content");

        if (!$resp->successful()) {
            return response()->json(['message' => 'No se pudo obtener el archivo desde Alfresco'], 502);
        }

        $ext  = pathinfo($doc->nombre_archivo, PATHINFO_EXTENSION);
        $mime = in_array($ext, ['jpg','jpeg','png']) ? "image/{$ext}" : 'application/pdf';

        return response($resp->body(), 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => "inline; filename=\"{$doc->nombre_archivo}\"",
        ]);
    }

    // Anular permiso aprobado (solo TH/ADMIN) — revierte el descuento de vacaciones
    public function anular(Request $request, $id)
    {
        $request->validate([
            'observacion_negacion' => 'required|string|max:120',
        ]);

        $actor = $request->user();

        if (!$this->esAdminOTH($actor->id_emp)) {
            return response()->json(['message' => 'Solo Talento Humano o Administrador puede anular un permiso aprobado'], 403);
        }

        $permiso = Permiso::findOrFail($id);

        if ($permiso->estado_permiso !== 'APROBADO') {
            return response()->json(['message' => 'Solo se pueden anular permisos en estado APROBADO'], 422);
        }

        // Revertir descuento de vacaciones si era descontable
        if ($permiso->descontable === 'SI') {
            $empleado     = Empleado::with('jornada')->find($permiso->id_emp);
            $horasJornada = $empleado?->jornada ? (float) $empleado->jornada->normal : 8.0;

            $factorFds = 30 / 22;

            if ($permiso->todo_dia === 'SI') {
                $diasBase      = Carbon::parse($permiso->fecha_desde)
                    ->diffInDays(Carbon::parse($permiso->fecha_hasta)) + 1;
                $diasDescuento = round($diasBase * $factorFds, 4);
            } else {
                $horas         = Carbon::parse($permiso->hora_desde)
                    ->diffInMinutes(Carbon::parse($permiso->hora_hasta)) / 60;
                $diasDescuento = round($horas / $horasJornada * $factorFds, 4);
            }

            $cabecera = CabeceraVacacion::where('id_emp', $permiso->id_emp)->first();
            if ($cabecera) {
                // Usar el monto efectivo guardado al aprobar (incluye el exceso sobre 60 que se consumió).
                // Si el permiso es anterior a este cambio, $dias_descuento_efectivo será null → usar diasDescuento.
                $efectivoRevertir = $permiso->dias_descuento_efectivo !== null
                    ? (float) $permiso->dias_descuento_efectivo
                    : $diasDescuento;

                $cabecera->dias_x_tomar_normal = round((float)($cabecera->dias_x_tomar_normal ?? 0) + $diasDescuento, 4);
                $cabecera->total_dias_tomados  = max(0, round((float)($cabecera->total_dias_tomados ?? 0) - $efectivoRevertir, 4));
                $cabecera->save();
            }
        }

        // NOTA: no hay reversión manual de d2_cuadre_marcacion aquí — al pasar el permiso a
        // ANULADO, ProcesarCuadre deja automáticamente de contarlo (solo suma permisos en
        // estado APROBADO) la próxima vez que se reprocese ese día. Si la fecha ya pasó y se
        // necesita corregir el cuadre ya generado, correr:
        // `php artisan procesar:cuadre --fecha=YYYY-MM-DD`

        $permiso->update([
            'estado_permiso'       => 'ANULADO',
            'observacion_negacion' => $request->observacion_negacion,
            'usuario'              => $actor->id_emp,
        ]);

        AuditoriaService::log('dbo.d2_permiso', $permiso->getKey(), 'ANULAR',
            ['estado_permiso' => 'APROBADO', 'descontable' => $permiso->descontable],
            ['estado_permiso' => 'ANULADO', 'observacion' => $request->observacion_negacion],
            $request, "Anulación de permiso aprobado: {$this->nombreEmpleadoPermiso($permiso)}");

        return response()->json([
            'message' => 'Permiso anulado y descuento revertido correctamente',
            'permiso' => $permiso->load(['empleado', 'razonPermiso']),
        ]);
    }

    // Listar razones
    public function razones()
    {
        return response()->json(Razon::where('estado', 'ACTIVO')->orderBy("descripcion")->get());
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

    // ─── Export helpers ────────────────────────────────────────────────────────

    private function exportarPermisosArchivo($items, string $formato, Request $request)
    {
        // Preparar filas
        $filas = [];
        foreach ($items as $p) {
            $nombreEmp = trim(($p->empleado->apellido_emp ?? '') . ' ' . ($p->empleado->nombre_emp ?? ''));
            $depto     = $p->empleado->departamento->descripcion ?? '';
            $razon     = $p->razonPermiso->descripcion ?? '';
            $filas[] = [
                'empleado'    => $nombreEmp,
                'depto'       => $depto,
                'razon'       => $razon,
                'tipo_horario'=> $p->tipo_horario ?? '',
                'fecha_desde' => $p->fecha_desde ? Carbon::parse($p->fecha_desde)->format('d/m/Y') : '',
                'fecha_hasta' => $p->fecha_hasta ? Carbon::parse($p->fecha_hasta)->format('d/m/Y') : '',
                'todo_dia'    => $p->todo_dia === 'SI' ? 'Sí' : 'No',
                'descontable' => $p->descontable === 'SI' ? 'Sí' : 'No',
                'estado'      => $p->estado_permiso ?? '',
            ];
        }

        if ($formato === 'excel') {
            return $this->exportarPermisosExcel($filas);
        }
        return $this->exportarPermisosPdf($filas, $request);
    }

    private function exportarPermisosExcel(array $filas)
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Permisos');

        // Encabezado institucional
        $sheet->setCellValue('A1', 'CONSEJO DE COMUNICACIÓN DEL ECUADOR');
        $sheet->setCellValue('A2', 'REPORTE DE PERMISOS Y LICENCIAS');
        $sheet->setCellValue('A3', 'Generado: ' . now()->format('d/m/Y H:i'));
        foreach (['A1','A2','A3'] as $c) {
            $sheet->getStyle($c)->getFont()->setBold(true);
        }

        // Cabeceras
        $headers = ['Empleado','Departamento','Razón','Tipo Horario','Fecha Desde','Fecha Hasta','Todo el Día','Descontable','Estado'];
        $cols    = ['A','B','C','D','E','F','G','H','I'];
        foreach ($headers as $i => $h) {
            $cell = $cols[$i] . '5';
            $sheet->setCellValue($cell, $h);
            $sheet->getStyle($cell)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0B5447']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Datos
        $row = 6;
        foreach ($filas as $f) {
            $sheet->setCellValue("A{$row}", $f['empleado']);
            $sheet->setCellValue("B{$row}", $f['depto']);
            $sheet->setCellValue("C{$row}", $f['razon']);
            $sheet->setCellValue("D{$row}", $f['tipo_horario']);
            $sheet->setCellValue("E{$row}", $f['fecha_desde']);
            $sheet->setCellValue("F{$row}", $f['fecha_hasta']);
            $sheet->setCellValue("G{$row}", $f['todo_dia']);
            $sheet->setCellValue("H{$row}", $f['descontable']);
            $sheet->setCellValue("I{$row}", $f['estado']);
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4FBF8']],
                ]);
            }
            $row++;
        }

        foreach ($cols as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer   = new Xlsx($spreadsheet);
        $filename = 'permisos_' . now()->format('Ymd_His') . '.xlsx';

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function exportarPermisosPdf(array $filas, Request $request)
    {
        $logo          = base64_encode(file_get_contents(public_path('logo.png')));
        $nombreInst    = 'CONSEJO DE COMUNICACIÓN DEL ECUADOR';
        $generadoPor   = trim($request->user()->apellido_emp . ' ' . $request->user()->nombre_emp);

        $pdf = Pdf::loadView('reportes.permisos_lista', compact('filas', 'logo', 'nombreInst', 'generadoPor'))
            ->setPaper('a4', 'landscape');

        return $pdf->stream('permisos_' . now()->format('Ymd_His') . '.pdf');
    }

}
