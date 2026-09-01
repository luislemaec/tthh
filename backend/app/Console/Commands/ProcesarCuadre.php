<?php
namespace App\Console\Commands;

use App\Models\Empleado;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcesarCuadre extends Command
{
    protected $signature   = 'procesar:cuadre {--fecha= : Fecha YYYY-MM-DD, default hoy}';
    protected $description = 'Procesa el cuadre de marcaciones para una fecha dada';

    public function handle(): int
    {
        $fecha = $this->option('fecha')
            ? Carbon::parse($this->option('fecha'))
            : Carbon::today();

        $this->info("Procesando cuadre para: {$fecha->toDateString()}");

        $diaMes   = (int) $fecha->format('j');  // día sin cero (1-30)
        $colTurno = 's' . $diaMes;

        // Cargar todos los turnos indexados
        $turnos = DB::table('dbo.d2_turno')->get()->groupBy('id_turno');

        // depto 999 = placeholder de sistema; es_externo=true siempre vive en depto 999 por diseño,
        // pero se filtra explícito también por si esa invariante alguna vez cambia. Ninguno de los
        // dos necesita cuadre de asistencia.
        $empleados  = Empleado::with(['departamento', 'jornada'])
            ->where('estado', 'ACTIVO')
            ->where('id_depto', '!=', 999)
            ->where(fn ($q) => $q->where('es_externo', false)->orWhereNull('es_externo'))
            ->get();
        $procesados = 0;

        foreach ($empleados as $emp) {
            // Obtener turno del día desde d2_programacion
            $prog    = DB::table('dbo.d2_programacion')
                ->where('id_emp', $emp->id_emp)
                ->whereYear('fecha', $fecha->year)
                ->whereMonth('fecha', $fecha->month)
                ->first();

            $idTurno  = $prog ? ((int)($prog->$colTurno ?? 1)) : 1;
            $turnoMap = isset($turnos[$idTurno])
                ? $turnos[$idTurno]->keyBy('concepto')
                : collect();

            // Horas programadas en decimal — null si el turno no tiene ese concepto configurado
            // (antes devolvía 0.0, lo que producía atrasos falsos gigantescos sin ningún aviso)
            $tEntrada  = $this->turnoHora($turnoMap, 'ENTRADA');
            $tSalLunch = $this->turnoHora($turnoMap, 'SALIDA AL LUNCH');
            $tEntLunch = $this->turnoHora($turnoMap, 'ENTRADA DEL LUNCH');
            $tSalida   = $this->turnoHora($turnoMap, 'SALIDA');

            $turnoIncompleto = in_array(null, [$tEntrada, $tSalLunch, $tEntLunch, $tSalida], true);
            if ($turnoIncompleto) {
                $this->warn("  Turno #{$idTurno} incompleto para {$emp->id_emp} ({$fecha->toDateString()}) — no se calculan atrasos, revisar configuración del turno.");
            }

            // Marcaciones del empleado para la fecha
            $marcaciones = DB::table('dbo.sg_control_persona')
                ->where('nro_documento', $emp->id_emp)
                ->whereDate('fecha_hora', $fecha)
                ->orderBy('fecha_hora')
                ->get();

            // Buscar por concepto; fallback por posición
            $mEntrada  = $marcaciones->firstWhere('concepto', 'ENTRADA')
                      ?? $marcaciones->get(0);
            $mSalLunch = $marcaciones->firstWhere('concepto', 'SALIDA AL LUNCH')
                      ?? $marcaciones->get(1);
            $mEntLunch = $marcaciones->firstWhere('concepto', 'ENTRADA DEL LUNCH')
                      ?? $marcaciones->get(2);
            $mSalida   = $marcaciones->firstWhere('concepto', 'SALIDA')
                      ?? $marcaciones->get(3);

            // Horas reales en decimal
            $rEntrada  = $mEntrada  ? $this->toDecimalHours($mEntrada->fecha_hora)  : null;
            $rSalLunch = $mSalLunch ? $this->toDecimalHours($mSalLunch->fecha_hora) : null;
            $rEntLunch = $mEntLunch ? $this->toDecimalHours($mEntLunch->fecha_hora) : null;
            $rSalida   = $mSalida   ? $this->toDecimalHours($mSalida->fecha_hora)   : null;

            // Permisos aprobados del empleado para este día
            $permisosHoy = DB::table('dbo.d2_permiso')
                ->where('id_emp', $emp->id_emp)
                ->where('estado_permiso', 'APROBADO')
                ->whereDate('fecha_desde', '<=', $fecha->toDateString())
                ->whereDate('fecha_hasta', '>=', $fecha->toDateString())
                ->get();

            // Límites justificados por permisos (en horas decimales)
            $entradaJustificada = null; // hora máxima justificada de llegada tardía
            $salidaJustificada  = null; // hora mínima justificada de salida anticipada
            foreach ($permisosHoy as $perm) {
                if ($perm->todo_dia === 'SI') {
                    // Permiso de día completo: no hay atrasos
                    $entradaJustificada = 99.0;
                    $salidaJustificada  = 0.0;
                    break;
                }
                $hDesde = $this->toDecimalHours($perm->hora_desde);
                $hHasta = $this->toDecimalHours($perm->hora_hasta);
                if ($perm->tipo_horario === 'ENTRADA') {
                    // Permiso de llegada tardía: justifica hasta hora_hasta
                    if ($entradaJustificada === null || $hHasta > $entradaJustificada) {
                        $entradaJustificada = $hHasta;
                    }
                } elseif ($perm->tipo_horario === 'SALIDA') {
                    // Permiso de salida anticipada: justifica desde hora_desde
                    if ($salidaJustificada === null || $hDesde < $salidaJustificada) {
                        $salidaJustificada = $hDesde;
                    }
                }
                // ENTRE JORNADA: afecta almuerzo o ausencia parcial — no se ajusta atraso_entrada/salida
            }

            // Atrasos en minutos (ajustados por permisos aprobados) — si el turno está incompleto
            // (algún concepto sin configurar), no se calcula ningún atraso: no hay con qué comparar
            // la hora real, y calcularlo igual producía atrasos falsos de horas enteras.
            if ($turnoIncompleto) {
                $atrasoEntrada = 0;
                $atrasoLunch   = 0;
                $atrasoSalida  = 0;
            } else {
                if ($rEntrada !== null) {
                    if ($entradaJustificada !== null && $rEntrada <= $entradaJustificada) {
                        // Llegó dentro del permiso: atraso = 0 o solo lo que exceda el permiso
                        $atrasoEntrada = max(0, round(($rEntrada - $entradaJustificada) * 60));
                    } else {
                        $atrasoEntrada = max(0, round(($rEntrada - $tEntrada) * 60));
                    }
                } else {
                    $atrasoEntrada = 0;
                }

                // Lunch = 30 minutos desde que timbró salida al lunch (sin importar la hora)
                if ($rEntLunch !== null && $rSalLunch !== null) {
                    $limiteRegreso = $rSalLunch + (30 / 60);
                    $atrasoLunch   = max(0, round((min($rEntLunch, $tSalida) - $limiteRegreso) * 60));
                } else {
                    $atrasoLunch = 0;
                }

                if ($rSalida !== null) {
                    if ($salidaJustificada !== null && $rSalida >= $salidaJustificada) {
                        // Salió a la hora del permiso o después: atraso = 0
                        $atrasoSalida = 0;
                    } elseif ($salidaJustificada !== null && $rSalida < $salidaJustificada) {
                        // Salió antes de que empiece el permiso: solo los minutos entre salida real y inicio permiso
                        $atrasoSalida = max(0, round(($salidaJustificada - $rSalida) * 60));
                    } else {
                        $atrasoSalida = max(0, round(($tSalida - $rSalida) * 60));
                    }
                } else {
                    $atrasoSalida = 0;
                }
            }

            // Horas a descontar (atrasos)
            $horasDecto = round(($atrasoEntrada + $atrasoLunch + $atrasoSalida) / 60, 4);

            // Aporte de permisos APROBADO a horas_decto (descontable=SI) / horaspermiso_pag
            // (descontable=NO) para este día. Se recalcula siempre desde d2_permiso (nunca se
            // acumula con +=) para que sea idempotente: da el mismo resultado sin importar
            // cuántas veces se reprocese el día, y refleja automáticamente una anulación
            // posterior (un permiso ANULADO simplemente deja de estar en $permisosHoy).
            // Mismo factor 30/22 que usa PermisosController::aprobar() (ver sección Permisos
            // en CLAUDE.md) para que el monto coincida con lo que se descontó del saldo de
            // vacaciones al aprobar.
            $factorFds        = 30 / 22;
            $horasDectoPermiso = 0.0;
            $horasPagoPermiso  = 0.0;
            foreach ($permisosHoy as $perm) {
                if ($perm->todo_dia === 'SI') {
                    // Día completo: aporta 1 por cada día calendario que el permiso cubre —
                    // como $permisosHoy ya viene filtrado a permisos que cubren $fecha, este
                    // día en particular siempre aporta exactamente 1 (nunca un valor
                    // fraccionario ni un rango de días mal calculado).
                    $aporte = 1.0;
                } else {
                    // Permiso por horas: es de un solo día (hora_desde/hora_hasta son horas
                    // del día fecha_desde) — solo aporta en ese día, no en todo el rango que
                    // devuelve la query de $permisosHoy.
                    if (!Carbon::parse($perm->fecha_desde)->isSameDay($fecha)) {
                        continue;
                    }
                    $horasPermiso = Carbon::parse($perm->hora_desde)->diffInMinutes(Carbon::parse($perm->hora_hasta)) / 60;
                    $horasJornadaPermiso = $emp->jornada ? (float) $emp->jornada->normal : 8.0;
                    $aporte       = round($horasPermiso / $horasJornadaPermiso * $factorFds, 4);
                }
                if ($perm->descontable === 'SI') {
                    $horasDectoPermiso += $aporte;
                } else {
                    $horasPagoPermiso += $aporte;
                }
            }
            $horasDecto      = round($horasDecto + $horasDectoPermiso, 4);
            $horasPermisoPag = round($horasPagoPermiso, 4);

            // Tiempo de almuerzo en minutos (real o programado — 0 si el turno no tiene esos conceptos)
            $tiempoLunch  = ($rEntLunch !== null && $rSalLunch !== null)
                ? (int) round(($rEntLunch - $rSalLunch) * 60)
                : (int) round((($tEntLunch ?? 0) - ($tSalLunch ?? 0)) * 60);

            // Horas totales trabajadas
            $horasTotales = null;
            if ($rEntrada !== null && $rSalida !== null) {
                $almuerzo     = ($rEntLunch !== null && $rSalLunch !== null)
                    ? ($rEntLunch - $rSalLunch)
                    : (($tEntLunch ?? 0) - ($tSalLunch ?? 0));
                $horasTotales = round(($rSalida - $rEntrada) - $almuerzo, 4);
            }

            // Falta: sin ninguna marcación
            $falta = ($mEntrada === null) ? 'S' : 'N';

            DB::table('dbo.d2_cuadre_marcacion')->updateOrInsert(
                [
                    'id_emp' => $emp->id_emp,
                    'fecha'  => $fecha->toDateString() . ' 00:00:00',
                ],
                [
                    'identificacion'       => trim($emp->identificacion),
                    'apellido'             => trim($emp->apellido_emp),
                    'nombre'               => trim($emp->nombre_emp),
                    'area'                 => $emp->departamento?->nombre_depto ?? '',
                    'jornada'              => $idTurno,
                    'hora_turno_entrada'   => $tEntrada,
                    'hora_real_entrada'    => $rEntrada,
                    'atraso_entrada'       => $atrasoEntrada,
                    'hora_turno_sal_lunch' => $tSalLunch,
                    'hora_real_sal_lunch'  => $rSalLunch,
                    'hora_turno_ent_lunch' => $tEntLunch,
                    'hora_real_ent_lunch'  => $rEntLunch,
                    'atraso_lunch'         => $atrasoLunch,
                    'hora_turno_sal'       => $tSalida,
                    'hora_real_sal'        => $rSalida,
                    'atraso_salida'        => $atrasoSalida,
                    'horas_totales'        => $horasTotales,
                    'horas_decto'          => $horasDecto,
                    'horaspermiso_pag'     => $horasPermisoPag,
                    'tiempo_lunch'         => $tiempoLunch,
                    'falta'                => $falta,
                    'ip'                   => '127.0.0.1',
                ]
            );

            $procesados++;
        }

        $this->info("Cuadre completado: {$procesados} empleados procesados.");
        return Command::SUCCESS;
    }

    // null si el turno no tiene ese concepto configurado — antes devolvía 0.0 (medianoche),
    // lo que hacía que un empleado marcando a su hora normal apareciera con horas de atraso.
    private function turnoHora($turnoMap, string $concepto): ?float
    {
        if (!isset($turnoMap[$concepto])) return null;
        $dt = Carbon::parse($turnoMap[$concepto]->hora);
        return round($dt->hour + $dt->minute / 60, 4);
    }

    private function toDecimalHours(string $fechaHora): float
    {
        $dt = Carbon::parse($fechaHora);
        return round($dt->hour + $dt->minute / 60, 4); // Trunca segundos
    }
}
