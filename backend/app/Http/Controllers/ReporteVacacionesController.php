<?php
namespace App\Http\Controllers;

use App\Models\CabeceraVacacion;
use App\Models\Configuracion;
use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\LiquidacionHistorico;
use App\Models\Vacacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteVacacionesController extends Controller
{
    private const MOTIVOS_CARGA_SALDO = ['FIN_COMISION_RETORNO', 'COMISION_ENTRANTE'];

    private const MOTIVO_LABEL = [
        'INICIO_COMISION'      => 'Inicio de comisión',
        'FIN_COMISION_SALIDA'  => 'Fin de comisión — salida',
        'FIN_COMISION_RETORNO' => 'Retorno de comisión — saldo cargado',
        'COMISION_ENTRANTE'    => 'Comisión entrante — saldo cargado',
        'DESVINCULACION'       => 'Desvinculación',
    ];

    private function esAdminOTH(Request $request): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $request->user()->id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])
            ->exists();
    }

    private function calcularSaldoActual(Empleado $emp): float
    {
        $tasas = ['LOSEP' => 2.50, 'CODIGO DEL TRABAJO' => 1.25];
        $tasa  = $tasas[trim($emp->tipo_contrato)] ?? 0;

        $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
        $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();

        if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte)) {
            $fechaCorte = Carbon::parse($emp->fecha_ingreso);
        }

        $diasCalendario = max(0, $fechaCorte->diffInDays(Carbon::today()));
        $diasAcumulados = round($diasCalendario / 360 * ($tasa * 12), 2);

        $cabecera = CabeceraVacacion::where('id_emp', $emp->id_emp)->first();
        if (!$cabecera) return $diasAcumulados;

        return max(0, round(
            (float)($cabecera->dias_adicionales  ?? 0)
            + $diasAcumulados
            - (float)($cabecera->total_dias_tomados ?? 0),
            2
        ));
    }

    // Días calendario incluyendo fines de semana — igual que aprobar() en VacacionesController
    private function diasVacacion(string $desde, string $hasta): float
    {
        return (float)(Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1);
    }

    // ── Listado resumido de empleados ────────────────────────────────────────
    public function index(Request $request)
    {
        if (!$this->esAdminOTH($request)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $query = Empleado::with('departamento')
            ->where('estado', 'ACTIVO')
            ->where('id_depto', '!=', 999);

        if ($request->id_depto) {
            $query->where('id_depto', $request->id_depto);
        }

        if ($request->buscar) {
            $q = $request->buscar;
            $query->where(function ($qb) use ($q) {
                $qb->whereRaw("CONCAT(apellido_emp, ' ', nombre_emp) ilike ?", ["%{$q}%"])
                   ->orWhere('identificacion', 'ilike', "%{$q}%");
            });
        }

        $empleados = $query->orderBy('apellido_emp')->orderBy('nombre_emp')->get();

        $cabecerasMap = CabeceraVacacion::whereIn('id_emp', $empleados->pluck('id_emp'))
            ->get()->keyBy('id_emp');

        $resultado = $empleados->map(function (Empleado $emp) use ($cabecerasMap) {
            $cabecera = $cabecerasMap->get($emp->id_emp);
            $tomados  = (float)($cabecera?->total_dias_tomados ?? 0);

            return [
                'id_emp'          => $emp->id_emp,
                'nombre_completo' => trim($emp->apellido_emp . ' ' . $emp->nombre_emp),
                'departamento'    => $emp->departamento?->nombre_depto ?? '—',
                'tipo_contrato'   => trim($emp->tipo_contrato ?? ''),
                'tomados'         => $tomados,
                'saldo_actual'    => $this->calcularSaldoActual($emp),
            ];
        });

        return response()->json($resultado);
    }

    // ── Kardex detallado de un empleado ──────────────────────────────────────
    public function detalle(Request $request, string $id_emp)
    {
        if (!$this->esAdminOTH($request)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $emp = Empleado::with('departamento')
            ->where('id_emp', $id_emp)
            ->where('id_depto', '!=', 999)
            ->firstOrFail();

        $cabecera = CabeceraVacacion::where('id_emp', $id_emp)->first();

        $liquidaciones = LiquidacionHistorico::where('id_emp', $id_emp)
            ->orderBy('fecha_evento', 'asc')
            ->get();

        // Fecha a partir de la cual se cuentan los días tomados actuales
        $ultimoReset = $liquidaciones
            ->whereIn('motivo', self::MOTIVOS_CARGA_SALDO)
            ->last();

        $fechaDesdeActual = $ultimoReset
            ? Carbon::parse($ultimoReset->fecha_evento)->toDateString()
            : null;

        // Vacaciones en el período actual (las que conforman total_dias_tomados)
        $vacQuery = Vacacion::where('id_emp', $id_emp)
            ->where('estado_permiso', 'APROBADO')
            ->orderBy('fecha_inicial', 'asc');

        if ($fechaDesdeActual) {
            $vacQuery->where('fecha_inicial', '>=', $fechaDesdeActual);
        }

        $vacaciones = $vacQuery->get();

        // Parámetros para el devengado
        $tasas = ['LOSEP' => 2.50, 'CODIGO DEL TRABAJO' => 1.25];
        $tasa  = $tasas[trim($emp->tipo_contrato)] ?? 0;

        $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
        $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();

        if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte)) {
            $fechaCorte = Carbon::parse($emp->fecha_ingreso);
        }

        $diasCalendario = max(0, $fechaCorte->diffInDays(Carbon::today()));
        $devengado      = round($diasCalendario / 360 * ($tasa * 12), 2);

        // Saldo inicial del período actual
        $diasAdicionales = (float)($cabecera?->dias_adicionales ?? 0);

        // Construir movimientos ────────────────────────────────────────────────
        $movimientos = [];

        // 1. Fila apertura
        $saldoAcum = $diasAdicionales;
        $movimientos[] = [
            'tipo'        => 'INICIAL',
            'fecha'       => null,
            'descripcion' => $ultimoReset
                ? 'Saldo cargado (' . (self::MOTIVO_LABEL[$ultimoReset->motivo] ?? $ultimoReset->motivo) . ')'
                : 'Saldo inicial',
            'entrada'     => $diasAdicionales,
            'salida'      => null,
            'saldo'       => $saldoAcum,
        ];

        // 2. Liquidaciones anteriores como contexto histórico (si las hay)
        //    Solo las que NO son el último reset (ese ya está en la fila apertura)
        $liquidacionesContexto = $ultimoReset
            ? $liquidaciones->filter(fn($l) => $l->id !== $ultimoReset->id)
            : $liquidaciones;

        foreach ($liquidacionesContexto as $liq) {
            $movimientos[] = [
                'tipo'        => 'LIQUIDACION',
                'fecha'       => $liq->fecha_evento,
                'descripcion' => self::MOTIVO_LABEL[$liq->motivo] ?? $liq->motivo,
                'entrada'     => null,
                'salida'      => null,
                'saldo'       => $liq->saldo_liquidado,
                'info'        => true,
            ];
        }

        // 3. Vacaciones tomadas — solo informativo, sin saldo por fila
        foreach ($vacaciones as $vac) {
            $dias = $this->diasVacacion($vac->fecha_inicial, $vac->fecha_final);
            $movimientos[] = [
                'tipo'        => 'VACACION',
                'fecha'       => $vac->fecha_inicial,
                'descripcion' => 'Vacaciones: ' . Carbon::parse($vac->fecha_inicial)->format('d/m/Y')
                    . ' al ' . Carbon::parse($vac->fecha_final)->format('d/m/Y'),
                'entrada'     => null,
                'salida'      => $dias,
                'saldo'       => null,
            ];
        }

        // 4. Subtotal tomados (valor exacto de cabecera — base del cálculo oficial)
        $totalTomados = (float)($cabecera?->total_dias_tomados ?? 0);
        $saldoTrasTomados = round($diasAdicionales - $totalTomados, 2);
        $movimientos[] = [
            'tipo'        => 'TOMADOS',
            'fecha'       => null,
            'descripcion' => 'Total días tomados',
            'entrada'     => null,
            'salida'      => $totalTomados,
            'saldo'       => $saldoTrasTomados,
        ];

        // 5. Devengado
        $saldoFinal = $this->calcularSaldoActual($emp);
        $movimientos[] = [
            'tipo'        => 'DEVENGADO',
            'fecha'       => Carbon::today()->toDateString(),
            'descripcion' => 'Días devengados a la fecha (desde ' . $fechaCorte->format('d/m/Y') . ')',
            'entrada'     => $devengado,
            'salida'      => null,
            'saldo'       => $saldoFinal,
        ];

        return response()->json([
            'empleado' => [
                'id_emp'          => $emp->id_emp,
                'nombre_completo' => trim($emp->apellido_emp . ' ' . $emp->nombre_emp),
                'departamento'    => $emp->departamento?->nombre_depto ?? '—',
                'tipo_contrato'   => trim($emp->tipo_contrato ?? ''),
            ],
            'movimientos' => $movimientos,
        ]);
    }
}
