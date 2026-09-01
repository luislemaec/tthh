<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Supervisor;
use App\Models\PlanificacionCab;
use App\Models\PlanificacionDet;
use App\Models\PeriodoPlanificacion;
use App\Models\CabeceraVacacion;
use App\Models\Configuracion;
use App\Services\AuditoriaService;
use App\Services\SaldoVacacionesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanificacionVacController extends Controller
{
    public function __construct(private SaldoVacacionesService $saldoService)
    {
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function esSupervisor($id_emp)
    {
        return Supervisor::where('id_supervisor', $id_emp)->exists();
    }

    private function esAdminOTH($id_emp)
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])
            ->exists();
    }

    // Empleados que supervisa, incluyendo supervisores de departamentos hijos
    // (mismo patrón que VacacionesController/PermisosController — antes esta versión
    // solo tenía el tramo directo, por lo que un supervisor de nivel superior no veía
    // las planificaciones de los empleados de un departamento hijo).
    private function empleadosDeSupervisor($id_supervisor)
    {
        $deptos = Supervisor::where('id_supervisor', $id_supervisor)->pluck('id_depto');

        $empleadosDirectos = Empleado::whereIn('id_depto', $deptos)
            ->where('estado', 'ACTIVO')
            ->where('id_emp', '!=', $id_supervisor)
            ->pluck('id_emp');

        $deptosHijos = DB::table('dbo.ad_departamento')
            ->whereIn('padre_id', $deptos)
            ->pluck('id_depto');

        $supervisoresHijos = Supervisor::whereIn('id_depto', $deptosHijos)
            ->where('id_supervisor', '!=', $id_supervisor)
            ->pluck('id_supervisor');

        return $empleadosDirectos->merge($supervisoresHijos)->unique()->values();
    }

    // Calcula el saldo real disponible — delegado a SaldoVacacionesService (única
    // fuente de verdad). Antes tenía su propia copia con tasa fija 1.25 para Código
    // del Trabajo (ignoraba antigüedad) y sin congelar en fecha_salida.
    private function calcularSaldo(Empleado $emp): float
    {
        return $this->saldoService->calcular($emp)['dias_disponibles'];
    }

    // Calcula días calendario entre dos fechas (inclusivo)
    private function diasEntreFechas(string $desde, string $hasta): int
    {
        return Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;
    }

    // Valida que los períodos no se solapen
    private function haysolapamiento(array $periodos): bool
    {
        $validos = array_filter($periodos, fn($p) => !empty($p['fecha_inicial']) && !empty($p['fecha_final']));
        $validos = array_values($validos);
        for ($i = 0; $i < count($validos); $i++) {
            for ($j = $i + 1; $j < count($validos); $j++) {
                $aInicio = Carbon::parse($validos[$i]['fecha_inicial']);
                $aFin    = Carbon::parse($validos[$i]['fecha_final']);
                $bInicio = Carbon::parse($validos[$j]['fecha_inicial']);
                $bFin    = Carbon::parse($validos[$j]['fecha_final']);
                if ($aInicio->lte($bFin) && $bInicio->lte($aFin)) return true;
            }
        }
        return false;
    }

    // ── Empleado ───────────────────────────────────────────────────────────────

    // Período activo + mi planificacion para ese año
    public function miPlanificacion(Request $request)
    {
        $emp = $request->user();
        $hoy = now()->toDateString();

        $periodo = PeriodoPlanificacion::where('estado', 'ACTIVO')
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin',    '>=', $hoy)
            ->first();

        // Si se pasa ?anio= usar ese año; si no, año actual por defecto
        $anioConsulta = (int) ($request->query('anio') ?? now()->year);

        $planificacion = PlanificacionCab::with('periodos')
            ->where('id_emp', $emp->id_emp)
            ->where('anio', $anioConsulta)
            ->whereNotIn('estado', ['ELIMINADO', 'NEGADO'])
            ->first();

        $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
        $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();

        if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte)) {
            // Empleado nuevo: calcular meses reales desde ingreso
            $mesesServicio   = Carbon::parse($emp->fecha_ingreso)->diffInMonths(Carbon::today());
            $puedeplanificar = $mesesServicio >= 11;
        } else {
            // Empleado existente (ingreso antes del corte): ya cumplió suficiente tiempo
            $mesesServicio   = 11; // mínimo requerido, ya cumplido
            $puedeplanificar = true;
        }

        return response()->json([
            'periodo'          => $periodo,
            'planificacion'    => $planificacion,
            'anio_consulta'    => $anioConsulta,
            'saldo'            => $this->calcularSaldo($emp),
            'meses_servicio'   => $mesesServicio,
            'puede_planificar' => $puedeplanificar,
        ]);
    }

    // Crear planificación (empleado)
    public function store(Request $request)
    {
        $request->validate([
            'anio'    => 'required|integer',
            'periodos'=> 'required|array|min:1|max:4',
            'periodos.*.fecha_inicial' => 'nullable|date',
            'periodos.*.fecha_final'   => 'nullable|date|nullable|after_or_equal:periodos.*.fecha_inicial',
        ]);

        $emp = $request->user();

        if ($emp->id_depto == 999) {
            return response()->json(['message' => 'El administrador no puede planificar vacaciones'], 403);
        }

        // Verificar 11 meses de servicio
        $fechaCorteConfig2 = Configuracion::find('FECHA_CORTE_VACACIONES');
        $fechaCorte2       = $fechaCorteConfig2 ? Carbon::parse($fechaCorteConfig2->valor) : Carbon::today();
        if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte2)) {
            $mesesServicio = Carbon::parse($emp->fecha_ingreso)->diffInMonths(Carbon::today());
        } else {
            $mesesServicio = 11; // empleado existente, ya cumplió
        }
        if ($mesesServicio < 11) {
            return response()->json([
                'message' => "Debes tener al menos 11 meses de servicio para planificar vacaciones (actualmente tienes {$mesesServicio} meses)"
            ], 422);
        }

        // Verificar período activo
        $hoy = now()->toDateString();
        $periodo = PeriodoPlanificacion::where('estado', 'ACTIVO')
            ->where('anio', $request->anio)
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin',    '>=', $hoy)
            ->first();

        if (!$periodo) {
            return response()->json(['message' => 'No hay un período de planificación activo para este año'], 422);
        }

        // Solo una planificación activa por empleado por año (ignora eliminadas y negadas)
        $existe = PlanificacionCab::where('id_emp', $emp->id_emp)
            ->where('anio', $request->anio)
            ->whereNotIn('estado', ['ELIMINADO', 'NEGADO'])
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'Ya tienes una planificación registrada para este año'], 422);
        }

        // Filtrar períodos con fechas completas
        $periodosValidos = array_filter($request->periodos, fn($p) =>
            !empty($p['fecha_inicial']) && !empty($p['fecha_final'])
        );

        if (empty($periodosValidos)) {
            return response()->json(['message' => 'Debes ingresar al menos un período con fechas completas'], 422);
        }

        // Validar que todos los períodos pertenezcan al año planificado
        foreach ($periodosValidos as $p) {
            $anioInicio = (int) Carbon::parse($p['fecha_inicial'])->format('Y');
            $anioFin    = (int) Carbon::parse($p['fecha_final'])->format('Y');
            if ($anioInicio !== (int) $request->anio || $anioFin !== (int) $request->anio) {
                return response()->json([
                    'message' => "Las fechas de los períodos deben pertenecer al año {$request->anio}."
                ], 422);
            }
        }

        // Validar solapamiento
        if ($this->haysolapamiento($request->periodos)) {
            return response()->json(['message' => 'Los períodos no pueden solaparse entre sí'], 422);
        }

        // Calcular total de días
        $totalDias = 0;
        foreach ($periodosValidos as $p) {
            $totalDias += $this->diasEntreFechas($p['fecha_inicial'], $p['fecha_final']);
        }

        // Validar máximo 30 días
        if ($totalDias > 30) {
            return response()->json([
                'message' => "La planificación no puede superar 30 días (actualmente: {$totalDias} días)"
            ], 422);
        }

        // Validar contra el saldo real disponible — antes solo se validaba el tope de
        // 30 días, así que un empleado con 10 días reales podía planificar hasta 30.
        $saldoDisponible = $this->calcularSaldo($emp);
        if ($totalDias > $saldoDisponible) {
            return response()->json([
                'message' => "No tienes suficiente saldo de vacaciones. Disponible: {$saldoDisponible} días, planificados: {$totalDias} días."
            ], 422);
        }

        // Supervisores auto-aprueban su propia planificación
        $esSupervisor = $this->esSupervisor($emp->id_emp);

        // Si existe un registro NEGADO o ELIMINADO, reutilizarlo (UPDATE) en vez de INSERT
        // para no violar la constraint unique(id_emp, anio)
        $cabExistente = PlanificacionCab::where('id_emp', $emp->id_emp)
            ->where('anio', $request->anio)
            ->whereIn('estado', ['NEGADO', 'ELIMINADO'])
            ->first();

        if ($cabExistente) {
            $cabExistente->update([
                'estado'                  => $esSupervisor ? 'APROBADO' : 'PENDIENTE',
                'total_dias_planificados' => $totalDias,
                'fecha_registro'          => now(),
                'usuario_registro'        => $emp->id_emp,
                'fecha_decision'          => $esSupervisor ? now() : null,
                'usuario_decision'        => $esSupervisor ? $emp->id_emp : null,
                'observacion'             => null,
                'replanificada'           => 'NO',
            ]);
            PlanificacionDet::where('cab_id', $cabExistente->id)->delete();
            $cab = $cabExistente;
        } else {
            $cab = PlanificacionCab::create([
                'id_emp'                  => $emp->id_emp,
                'anio'                    => $request->anio,
                'estado'                  => $esSupervisor ? 'APROBADO' : 'PENDIENTE',
                'total_dias_planificados' => $totalDias,
                'fecha_registro'          => now(),
                'usuario_registro'        => $emp->id_emp,
                'fecha_decision'          => $esSupervisor ? now() : null,
                'usuario_decision'        => $esSupervisor ? $emp->id_emp : null,
                'replanificada'           => 'NO',
            ]);
        }

        // Crear períodos
        foreach ($request->periodos as $i => $p) {
            $dias = (!empty($p['fecha_inicial']) && !empty($p['fecha_final']))
                ? $this->diasEntreFechas($p['fecha_inicial'], $p['fecha_final'])
                : null;

            PlanificacionDet::create([
                'cab_id'         => $cab->id,
                'numero_periodo' => $i + 1,
                'fecha_inicial'  => $p['fecha_inicial'] ?: null,
                'fecha_final'    => $p['fecha_final']   ?: null,
                'dias_calculados'=> $dias,
            ]);
        }

        AuditoriaService::log('dbo.vac_planificacion_cab', $cab->id, 'CREAR',
            null,
            ['anio' => $cab->anio, 'estado' => $cab->estado, 'total_dias_planificados' => $totalDias],
            $request, "Planificación de vacaciones " . ($esSupervisor ? 'creada y auto-aprobada' : 'creada') . ": " . trim($emp->apellido_emp . ' ' . $emp->nombre_emp));

        return response()->json($cab->load('periodos'), 201);
    }

    // ── Supervisor ─────────────────────────────────────────────────────────────

    // Listar planificaciones del equipo
    public function index(Request $request)
    {
        $emp         = $request->user();
        $esAdminOTH  = $this->esAdminOTH($emp->id_emp);
        $esSupervisor = $this->esSupervisor($emp->id_emp);

        $query = PlanificacionCab::with(['empleado.departamento', 'periodos'])
            ->orderByDesc('anio')
            ->orderBy('estado');

        if ($esAdminOTH) {
            // Ve todas
        } elseif ($esSupervisor) {
            $empleados = $this->empleadosDeSupervisor($emp->id_emp);
            $query->whereIn('id_emp', $empleados);
        } else {
            $query->where('id_emp', $emp->id_emp);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('anio')) {
            $query->where('anio', $request->anio);
        }

        return response()->json($query->get());
    }

    // Aprobar planificación
    public function aprobar(Request $request, $id)
    {
        $planificacion = PlanificacionCab::findOrFail($id);
        $supervisor    = $request->user();

        if ($planificacion->id_emp === $supervisor->id_emp) {
            return response()->json(['message' => 'No puedes aprobar tu propia planificación'], 403);
        }

        $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
        if (!$this->esAdminOTH($supervisor->id_emp) && !$empleados->contains($planificacion->id_emp)) {
            return response()->json(['message' => 'No eres supervisor de este empleado'], 403);
        }

        if ($planificacion->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'La planificación no está en estado PENDIENTE'], 422);
        }

        $planificacion->update([
            'estado'          => 'APROBADO',
            'fecha_decision'  => now(),
            'usuario_decision'=> $supervisor->id_emp,
        ]);

        AuditoriaService::log('dbo.vac_planificacion_cab', $planificacion->id, 'APROBAR',
            ['estado' => 'PENDIENTE'],
            ['estado' => 'APROBADO'],
            $request, "Aprobación de planificación de vacaciones: " . trim(($planificacion->empleado->apellido_emp ?? '') . ' ' . ($planificacion->empleado->nombre_emp ?? '')));

        return response()->json(['message' => 'Planificación aprobada', 'planificacion' => $planificacion->load('periodos')]);
    }

    // Negar planificación
    public function negar(Request $request, $id)
    {
        $request->validate([
            'observacion' => 'required|string|max:250',
        ]);

        $planificacion = PlanificacionCab::findOrFail($id);
        $supervisor    = $request->user();

        if ($planificacion->id_emp === $supervisor->id_emp) {
            return response()->json(['message' => 'No puedes negar tu propia planificación'], 403);
        }

        $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
        if (!$this->esAdminOTH($supervisor->id_emp) && !$empleados->contains($planificacion->id_emp)) {
            return response()->json(['message' => 'No eres supervisor de este empleado'], 403);
        }

        if ($planificacion->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'La planificación no está en estado PENDIENTE'], 422);
        }

        $planificacion->update([
            'estado'          => 'NEGADO',
            'fecha_decision'  => now(),
            'usuario_decision'=> $supervisor->id_emp,
            'observacion'     => $request->observacion,
        ]);

        AuditoriaService::log('dbo.vac_planificacion_cab', $planificacion->id, 'NEGAR',
            ['estado' => 'PENDIENTE'],
            ['estado' => 'NEGADO', 'observacion' => $request->observacion],
            $request, "Negación de planificación de vacaciones: " . trim(($planificacion->empleado->apellido_emp ?? '') . ' ' . ($planificacion->empleado->nombre_emp ?? '')));

        return response()->json(['message' => 'Planificación negada']);
    }

    // Eliminar planificación
    public function destroy(Request $request, $id)
    {
        $request->validate([
            'observacion' => 'required|string|max:250',
        ]);

        $planificacion = PlanificacionCab::findOrFail($id);
        $supervisor    = $request->user();

        $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
        if (!$this->esAdminOTH($supervisor->id_emp) && !$empleados->contains($planificacion->id_emp)) {
            return response()->json(['message' => 'No eres supervisor de este empleado'], 403);
        }

        if ($planificacion->estado !== 'PENDIENTE') {
            return response()->json(['message' => 'Solo se pueden eliminar planificaciones en estado PENDIENTE'], 422);
        }

        $planificacion->update([
            'estado'          => 'ELIMINADO',
            'fecha_decision'  => now(),
            'usuario_decision'=> $supervisor->id_emp,
            'observacion'     => $request->observacion,
        ]);

        AuditoriaService::log('dbo.vac_planificacion_cab', $planificacion->id, 'ELIMINAR',
            ['estado' => 'PENDIENTE', 'total_dias_planificados' => $planificacion->total_dias_planificados],
            ['estado' => 'ELIMINADO', 'observacion' => $request->observacion],
            $request, "Eliminación de planificación de vacaciones: " . trim(($planificacion->empleado->apellido_emp ?? '') . ' ' . ($planificacion->empleado->nombre_emp ?? '')));

        return response()->json(['message' => 'Planificación eliminada']);
    }

    // Replanificar (solo APROBADO, solo una vez)
    public function replanificar(Request $request, $id)
    {
        $request->validate([
            'periodos'=> 'required|array|min:1|max:4',
            'periodos.*.fecha_inicial' => 'nullable|date',
            'periodos.*.fecha_final'   => 'nullable|date',
        ]);

        $planificacion = PlanificacionCab::with('periodos')->findOrFail($id);
        $supervisor    = $request->user();

        $empleados = $this->empleadosDeSupervisor($supervisor->id_emp);
        if (!$this->esAdminOTH($supervisor->id_emp) && !$empleados->contains($planificacion->id_emp)) {
            return response()->json(['message' => 'No eres supervisor de este empleado'], 403);
        }

        if ($planificacion->estado !== 'APROBADO') {
            return response()->json(['message' => 'Solo se puede replanificar una planificación APROBADA'], 422);
        }

        if ($planificacion->replanificada === 'SI') {
            return response()->json(['message' => 'Esta planificación ya fue replanificada anteriormente'], 422);
        }

        // Validar períodos
        $periodosValidos = array_filter($request->periodos, fn($p) =>
            !empty($p['fecha_inicial']) && !empty($p['fecha_final'])
        );

        if (empty($periodosValidos)) {
            return response()->json(['message' => 'Debes ingresar al menos un período con fechas completas'], 422);
        }

        // Validar que los períodos de replanificación sean del año actual
        $anioActual = (int) now()->format('Y');
        foreach ($periodosValidos as $p) {
            $anioInicio = (int) Carbon::parse($p['fecha_inicial'])->format('Y');
            $anioFin    = (int) Carbon::parse($p['fecha_final'])->format('Y');
            if ($anioInicio !== $anioActual || $anioFin !== $anioActual) {
                return response()->json([
                    'message' => "Las fechas de la replanificación deben pertenecer al año {$anioActual}."
                ], 422);
            }
        }

        if ($this->haysolapamiento($request->periodos)) {
            return response()->json(['message' => 'Los períodos no pueden solaparse entre sí'], 422);
        }

        // Calcular nuevo total
        $totalDias = 0;
        foreach ($periodosValidos as $p) {
            $totalDias += $this->diasEntreFechas($p['fecha_inicial'], $p['fecha_final']);
        }

        // Validar máximo 30 días
        if ($totalDias > 30) {
            return response()->json([
                'message' => "La replanificación no puede superar 30 días (actualmente: {$totalDias} días)"
            ], 422);
        }

        // Validar contra el saldo real disponible (mismo criterio que store())
        $empPlanificado = Empleado::find($planificacion->id_emp);
        if ($empPlanificado) {
            $saldoDisponible = $this->calcularSaldo($empPlanificado);
            if ($totalDias > $saldoDisponible) {
                return response()->json([
                    'message' => "No tienes suficiente saldo de vacaciones. Disponible: {$saldoDisponible} días, replanificados: {$totalDias} días."
                ], 422);
            }
        }

        $totalDiasAnterior = $planificacion->total_dias_planificados;

        // Eliminar períodos anteriores y crear nuevos
        PlanificacionDet::where('cab_id', $planificacion->id)->delete();

        foreach ($request->periodos as $i => $p) {
            $dias = (!empty($p['fecha_inicial']) && !empty($p['fecha_final']))
                ? $this->diasEntreFechas($p['fecha_inicial'], $p['fecha_final'])
                : null;

            PlanificacionDet::create([
                'cab_id'         => $planificacion->id,
                'numero_periodo' => $i + 1,
                'fecha_inicial'  => $p['fecha_inicial'] ?: null,
                'fecha_final'    => $p['fecha_final']   ?: null,
                'dias_calculados'=> $dias,
            ]);
        }

        $planificacion->update([
            'estado'                  => 'REPLANIFICADO',
            'replanificada'           => 'SI',
            'total_dias_planificados' => $totalDias,
            'fecha_decision'          => now(),
            'usuario_decision'        => $supervisor->id_emp,
        ]);

        AuditoriaService::log('dbo.vac_planificacion_cab', $planificacion->id, 'REPLANIFICAR',
            ['estado' => 'APROBADO', 'total_dias_planificados' => $totalDiasAnterior],
            ['estado' => 'REPLANIFICADO', 'total_dias_planificados' => $totalDias],
            $request, "Replanificación de vacaciones: " . trim(($planificacion->empleado->apellido_emp ?? '') . ' ' . ($planificacion->empleado->nombre_emp ?? '')));

        return response()->json([
            'message'       => 'Planificación replanificada correctamente',
            'planificacion' => $planificacion->load('periodos'),
        ]);
    }
}
