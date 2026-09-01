<?php

namespace App\Services;

use App\Models\CabeceraVacacion;
use App\Models\Configuracion;
use App\Models\Empleado;
use Carbon\Carbon;

/**
 * Cálculo único del saldo de vacaciones — antes duplicado, con reglas divergentes,
 * en VacacionesController::calcularSaldoDisponible(), ReporteVacacionesController::
 * calcularSaldoActual()/calcularSaldoActualReal(), PermisosController::calcularInternoVac()
 * y PlanificacionVacController::calcularSaldo() (esta última con tasa fija 1.25 para
 * Código del Trabajo, ignorando antigüedad, y sin congelar en fecha_salida).
 *
 * Reglas de negocio (ver sección "Vacaciones — cálculo de saldo" en CLAUDE.md):
 * - LOSEP: 2.50 días/mes fijo (30 días/año)
 * - Código del Trabajo: tasa variable por antigüedad, Art. 69 — 15 días base + 1 día
 *   adicional por cada año desde el 6to, tope +15 (30 días/año desde los 20 años)
 * - Empleados INACTIVOS con fecha_salida: el acumulado se congela ahí, no sigue
 *   creciendo hasta hoy
 * - Tope de 60 días visibles/solicitables (LOSEP Art. 29) — el acumulado interno
 *   sigue corriendo sin límite, solo lo que se muestra/valida tiene el tope
 */
class SaldoVacacionesService
{
    // Tasa mensual y días anuales según tipo de contrato y antigüedad (Art. 69 CT).
    // $fechaHasta permite calcular la antigüedad a una fecha distinta de hoy (empleado
    // ya INACTIVO con acumulado congelado en su fecha_salida).
    public function tasaVacaciones(Empleado $emp, ?Carbon $fechaHasta = null): array
    {
        $contrato = trim($emp->tipo_contrato ?? '');

        if ($contrato === 'LOSEP') {
            return ['tasa_mensual' => 2.50, 'dias_anuales' => 30, 'dias_adicionales_antiguedad' => 0];
        }

        if ($contrato === 'CODIGO DEL TRABAJO') {
            $hasta       = $fechaHasta ?? Carbon::today();
            $anios       = $emp->fecha_ingreso ? (int) Carbon::parse($emp->fecha_ingreso)->diffInYears($hasta) : 0;
            $diasExtra   = min(max(0, $anios - 5), 15);
            $diasAnuales = 15 + $diasExtra;
            return [
                'tasa_mensual'                 => $diasAnuales / 12,
                'dias_anuales'                 => $diasAnuales,
                'dias_adicionales_antiguedad'  => $diasExtra,
            ];
        }

        return ['tasa_mensual' => 0, 'dias_anuales' => 0, 'dias_adicionales_antiguedad' => 0];
    }

    // Fecha hasta la que se acumula: fecha_salida si el empleado ya está INACTIVO
    // (el acumulado se congela ahí), si no, hoy.
    public function fechaHastaAcumulacion(Empleado $emp): Carbon
    {
        $estaInactivo = strtoupper(trim($emp->estado ?? '')) === 'INACTIVO';
        return ($estaInactivo && $emp->fecha_salida) ? Carbon::parse($emp->fecha_salida) : Carbon::today();
    }

    // Cálculo completo del saldo — mismo shape para todos los llamadores.
    // $cabecera se busca sola si no se pasa; si no existe ninguna fila de cabecera
    // (empleado sin cabecera todavía cargada), se calcula igual con saldo_inicial=0
    // y tomados=0.
    public function calcular(Empleado $emp, ?CabeceraVacacion $cabecera = null): array
    {
        $cabecera ??= CabeceraVacacion::where('id_emp', $emp->id_emp)->first();

        $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
        $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();
        if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte)) {
            $fechaCorte = Carbon::parse($emp->fecha_ingreso);
        }

        $fechaHasta = $this->fechaHastaAcumulacion($emp);
        $info       = $this->tasaVacaciones($emp, $fechaHasta);

        $diasCalendario = max(0, $fechaCorte->diffInDays($fechaHasta));
        $diasAcumulados = round($diasCalendario / 360 * ($info['tasa_mensual'] * 12), 2);

        $saldoInicial = (float) ($cabecera->dias_adicionales   ?? 0);
        $tomados      = (float) ($cabecera->total_dias_tomados ?? 0);
        $disponibles  = round($saldoInicial + $diasAcumulados - $tomados, 2);

        return [
            'saldo_inicial'               => $saldoInicial,
            'acumulado_a_hoy'             => $diasAcumulados,
            'tomados'                     => $tomados,
            'dias_disponibles'            => min(60, max(0, $disponibles)),   // tope LOSEP Art. 29, nunca negativo
            'dias_disponibles_real'       => min(60, $disponibles),           // puede ser negativo (Nombramiento Definitivo)
            'dias_anuales'                => $info['dias_anuales'],
            'dias_adicionales_antiguedad' => $info['dias_adicionales_antiguedad'],
        ];
    }

    // Saldo interno SIN el tope de 60 — para saber cuánto excede el tope al aprobar
    // un permiso descontable (ver migración 000100 / PermisosController::aprobar()).
    public function calcularInterno(Empleado $emp, ?CabeceraVacacion $cabecera = null): float
    {
        $cabecera ??= CabeceraVacacion::where('id_emp', $emp->id_emp)->first();
        if (!$cabecera) return 0.0;

        $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
        $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();
        if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte)) {
            $fechaCorte = Carbon::parse($emp->fecha_ingreso);
        }

        $fechaHasta = $this->fechaHastaAcumulacion($emp);
        $tasa       = $this->tasaVacaciones($emp, $fechaHasta)['tasa_mensual'];

        $diasCalendario = max(0, $fechaCorte->diffInDays($fechaHasta));
        $diasAcumulados = round($diasCalendario / 360 * ($tasa * 12), 2);

        $saldoInicial = (float) ($cabecera->dias_adicionales   ?? 0);
        $tomados      = (float) ($cabecera->total_dias_tomados ?? 0);

        return $saldoInicial + $diasAcumulados - $tomados;
    }
}
