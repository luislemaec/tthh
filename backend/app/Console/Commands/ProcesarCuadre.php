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

        $empleados  = Empleado::with('departamento')->where('estado', 'ACTIVO')->get();
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

            // Horas programadas en decimal
            $tEntrada  = $this->turnoHora($turnoMap, 'ENTRADA');
            $tSalLunch = $this->turnoHora($turnoMap, 'SALIDA AL LUNCH');
            $tEntLunch = $this->turnoHora($turnoMap, 'ENTRADA DEL LUNCH');
            $tSalida   = $this->turnoHora($turnoMap, 'SALIDA');

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

            // Atrasos en minutos (ajustados por permisos aprobados)
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
                $atrasoLunch   = max(0, round(($rEntLunch - $limiteRegreso) * 60));
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

            // Horas a descontar
            $horasDecto = round(($atrasoEntrada + $atrasoLunch + $atrasoSalida) / 60, 4);

            // Tiempo de almuerzo en minutos (real o programado)
            $tiempoLunch  = ($rEntLunch !== null && $rSalLunch !== null)
                ? (int) round(($rEntLunch - $rSalLunch) * 60)
                : (int) round(($tEntLunch - $tSalLunch) * 60);

            // Horas totales trabajadas
            $horasTotales = null;
            if ($rEntrada !== null && $rSalida !== null) {
                $almuerzo     = ($rEntLunch !== null && $rSalLunch !== null)
                    ? ($rEntLunch - $rSalLunch)
                    : ($tEntLunch - $tSalLunch);
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

    private function turnoHora($turnoMap, string $concepto): float
    {
        if (!isset($turnoMap[$concepto])) return 0.0;
        $dt = Carbon::parse($turnoMap[$concepto]->hora);
        return round($dt->hour + $dt->minute / 60, 4);
    }

    private function toDecimalHours(string $fechaHora): float
    {
        $dt = Carbon::parse($fechaHora);
        return round($dt->hour + $dt->minute / 60, 4); // Trunca segundos
    }
}
