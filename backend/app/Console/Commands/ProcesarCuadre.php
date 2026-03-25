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

            // Atrasos en minutos
            $atrasoEntrada = $rEntrada  !== null ? max(0, round(($rEntrada  - $tEntrada)  * 60)) : 0;
            // Lunch = 30 minutos desde que timbró salida al lunch (sin importar la hora)
            if ($rEntLunch !== null && $rSalLunch !== null) {
                $limiteRegreso = $rSalLunch + (30 / 60);
                $atrasoLunch   = max(0, round(($rEntLunch - $limiteRegreso) * 60));
            } else {
                $atrasoLunch = 0;
            }
            $atrasoSalida  = $rSalida   !== null ? max(0, round(($tSalida   - $rSalida)   * 60)) : 0;

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
