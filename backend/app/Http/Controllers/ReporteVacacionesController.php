<?php
namespace App\Http\Controllers;

use App\Models\CabeceraVacacion;
use App\Models\Configuracion;
use App\Models\Empleado;
use App\Models\LiquidacionHistorico;
use App\Models\Vacacion;
use Barryvdh\DomPDF\Facade\Pdf;
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

    private function esAdministrador(Request $request): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $request->user()->id_emp)
            ->where('r.descripcion', 'ADMINISTRADOR')
            ->exists();
    }

    private function tasaVacaciones(Empleado $emp): array
    {
        $contrato = trim($emp->tipo_contrato ?? '');
        if ($contrato === 'LOSEP') {
            return ['tasa_mensual' => 2.50, 'dias_anuales' => 30, 'dias_adicionales_antiguedad' => 0];
        }
        if ($contrato === 'CODIGO DEL TRABAJO') {
            $anios     = $emp->fecha_ingreso ? (int) Carbon::parse($emp->fecha_ingreso)->diffInYears(Carbon::today()) : 0;
            $diasExtra = min(max(0, $anios - 5), 15);
            $diasAnuales = 15 + $diasExtra;
            return ['tasa_mensual' => $diasAnuales / 12, 'dias_anuales' => $diasAnuales, 'dias_adicionales_antiguedad' => $diasExtra];
        }
        return ['tasa_mensual' => 0, 'dias_anuales' => 0, 'dias_adicionales_antiguedad' => 0];
    }

    private function calcularSaldoActual(Empleado $emp): float
    {
        $tasa = $this->tasaVacaciones($emp)['tasa_mensual'];

        $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
        $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();

        if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte)) {
            $fechaCorte = Carbon::parse($emp->fecha_ingreso);
        }

        // INACTIVO con fecha_salida: acumular solo hasta esa fecha (no hasta hoy)
        $fechaHasta = Carbon::today();
        if ($emp->estado === 'INACTIVO' && !empty($emp->fecha_salida)) {
            $fechaHasta = Carbon::parse($emp->fecha_salida);
        }

        $diasCalendario = max(0, $fechaCorte->diffInDays($fechaHasta));
        $diasAcumulados = round($diasCalendario / 360 * ($tasa * 12), 2);

        $cabecera = CabeceraVacacion::where('id_emp', $emp->id_emp)->first();
        if (!$cabecera) return $diasAcumulados;

        return min(60, max(0, round(
            (float)($cabecera->dias_adicionales  ?? 0)
            + $diasAcumulados
            - (float)($cabecera->total_dias_tomados ?? 0),
            2
        )));
    }

    // Igual que aprobar() en VacacionesController: días calendario inclusivos
    private function diasVacacion(string $desde, string $hasta): float
    {
        return (float)(Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1);
    }

    // Construye los movimientos del kardex para un empleado
    private function buildKardex(Empleado $emp): array
    {
        $cabecera = CabeceraVacacion::where('id_emp', $emp->id_emp)->first();

        $liquidaciones = LiquidacionHistorico::where('id_emp', $emp->id_emp)
            ->orderBy('fecha_evento', 'asc')
            ->get();

        $ultimoReset = $liquidaciones->whereIn('motivo', self::MOTIVOS_CARGA_SALDO)->last();

        $fechaDesdeActual = $ultimoReset
            ? Carbon::parse($ultimoReset->fecha_evento)->toDateString()
            : null;

        $vacQuery = Vacacion::where('id_emp', $emp->id_emp)
            ->where('estado_permiso', 'APROBADO')
            ->orderBy('fecha_inicial', 'asc');
        if ($fechaDesdeActual) {
            $vacQuery->where('fecha_inicial', '>=', $fechaDesdeActual);
        }
        $vacaciones = $vacQuery->get();

        $infoTasa = $this->tasaVacaciones($emp);
        $tasa     = $infoTasa['tasa_mensual'];

        $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
        $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();

        if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte)) {
            $fechaCorte = Carbon::parse($emp->fecha_ingreso);
        }

        // INACTIVO con fecha_salida: acumular solo hasta esa fecha
        $fechaHastaKardex = Carbon::today();
        if ($emp->estado === 'INACTIVO' && !empty($emp->fecha_salida)) {
            $fechaHastaKardex = Carbon::parse($emp->fecha_salida);
        }

        $diasCalendario  = max(0, $fechaCorte->diffInDays($fechaHastaKardex));
        $acumulado       = round($diasCalendario / 360 * ($tasa * 12), 2);
        $diasAdicionales = (float)($cabecera?->dias_adicionales ?? 0);
        $totalTomados    = (float)($cabecera?->total_dias_tomados ?? 0);
        $saldoFinal      = $this->calcularSaldoActual($emp);

        $movimientos = [];

        // 1. Saldo inicial — fecha = FECHA_CORTE_VACACIONES
        $movimientos[] = [
            'tipo'        => 'INICIAL',
            'fecha'       => $fechaCorte->toDateString(),
            'descripcion' => $ultimoReset
                ? 'Saldo cargado (' . (self::MOTIVO_LABEL[$ultimoReset->motivo] ?? $ultimoReset->motivo) . ')'
                : 'Saldo inicial',
            'entrada'     => $diasAdicionales,
            'salida'      => null,
            'saldo'       => $diasAdicionales,
        ];

        // 2. Liquidaciones históricas (contexto)
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
            ];
        }

        // 3. Vacaciones — fecha = aprobado_en (cuando el jefe aprobó)
        $diasIndividuales = 0;
        foreach ($vacaciones as $vac) {
            $dias = $this->diasVacacion($vac->fecha_inicial, $vac->fecha_final);
            $diasIndividuales += $dias;
            $movimientos[] = [
                'tipo'        => 'VACACION',
                'fecha'       => $vac->aprobado_en
                    ? Carbon::parse($vac->aprobado_en)->toDateString()
                    : $vac->fecha_inicial,
                'descripcion' => 'Vacaciones: ' . Carbon::parse($vac->fecha_inicial)->format('d/m/Y')
                    . ' al ' . Carbon::parse($vac->fecha_final)->format('d/m/Y'),
                'entrada'     => null,
                'salida'      => $dias,
                'saldo'       => null,
            ];
        }

        // Si hay días tomados que no están respaldados por registros individuales (datos legados)
        $diasLegado = round($totalTomados - $diasIndividuales, 2);
        if ($diasLegado > 0) {
            $movimientos[] = [
                'tipo'        => 'VACACION',
                'fecha'       => null,
                'descripcion' => 'Descuentos por permisos y vacaciones anteriores al sistema',
                'entrada'     => null,
                'salida'      => $diasLegado,
                'saldo'       => null,
            ];
        }

        // 4. Días acumulados a la fecha — fecha = hoy
        $descDevengado = 'Días acumulados a la fecha (desde ' . $fechaCorte->format('d/m/Y') . ')';
        if ($infoTasa['dias_adicionales_antiguedad'] > 0) {
            $descDevengado .= ' — ' . $infoTasa['dias_anuales'] . ' días/año (15 base + ' . $infoTasa['dias_adicionales_antiguedad'] . ' por antigüedad)';
        }
        $movimientos[] = [
            'tipo'        => 'DEVENGADO',
            'fecha'       => Carbon::today()->toDateString(),
            'descripcion' => $descDevengado,
            'entrada'     => $acumulado,
            'salida'      => null,
            'saldo'       => null,
        ];

        // 5. Total tomados al final — con saldo final exacto
        $movimientos[] = [
            'tipo'        => 'TOMADOS',
            'fecha'       => null,
            'descripcion' => 'Total días tomados',
            'entrada'     => null,
            'salida'      => $totalTomados,
            'saldo'       => $saldoFinal,
        ];

        return $movimientos;
    }

    // ── Listado resumido ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $esAdmin = $this->esAdminOTH($request);

        // Empleado sin rol TH/ADMIN: solo ve su propio saldo
        if (!$esAdmin) {
            $emp      = $request->user();
            $cabecera = CabeceraVacacion::where('id_emp', $emp->id_emp)->first();
            return response()->json([[
                'id_emp'          => $emp->id_emp,
                'nombre_completo' => trim($emp->apellido_emp . ' ' . $emp->nombre_emp),
                'departamento'    => '',
                'tipo_contrato'   => trim($emp->tipo_contrato ?? ''),
                'tomados'         => (float)($cabecera?->total_dias_tomados ?? 0),
                'saldo_actual'    => $this->calcularSaldoActual($emp),
            ]]);
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

        $empleados    = $query->orderBy('apellido_emp')->orderBy('nombre_emp')->get();
        $cabecerasMap = CabeceraVacacion::whereIn('id_emp', $empleados->pluck('id_emp'))
            ->get()->keyBy('id_emp');

        $resultado = $empleados->map(function (Empleado $emp) use ($cabecerasMap) {
            $cabecera = $cabecerasMap->get($emp->id_emp);
            return [
                'id_emp'          => $emp->id_emp,
                'nombre_completo' => trim($emp->apellido_emp . ' ' . $emp->nombre_emp),
                'departamento'    => $emp->departamento?->nombre_depto ?? '—',
                'tipo_contrato'   => trim($emp->tipo_contrato ?? ''),
                'tomados'         => (float)($cabecera?->total_dias_tomados ?? 0),
                'saldo_actual'    => $this->calcularSaldoActual($emp),
            ];
        });

        return response()->json($resultado);
    }

    // ── Kardex de un empleado ────────────────────────────────────────────────
    public function detalle(Request $request, string $id_emp)
    {
        // Empleado normal solo puede ver su propio kardex
        if (!$this->esAdminOTH($request) && $request->user()->id_emp !== $id_emp) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $emp = Empleado::with('departamento')
            ->where('id_emp', $id_emp)
            ->where('id_depto', '!=', 999)
            ->firstOrFail();

        return response()->json([
            'empleado' => [
                'id_emp'          => $emp->id_emp,
                'nombre_completo' => trim($emp->apellido_emp . ' ' . $emp->nombre_emp),
                'departamento'    => $emp->departamento?->nombre_depto ?? '—',
                'tipo_contrato'   => trim($emp->tipo_contrato ?? ''),
            ],
            'movimientos' => $this->buildKardex($emp),
        ]);
    }

    // ── Editar saldo de un empleado específico ──────────────────────────────
    public function actualizarSaldo(Request $request, string $id_emp)
    {
        if (!$this->esAdministrador($request)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $request->validate([
            'dias_adicionales' => 'required|numeric|min:0',
        ]);

        $emp = Empleado::where('id_emp', $id_emp)
            ->where('id_depto', '!=', 999)
            ->firstOrFail();

        CabeceraVacacion::updateOrCreate(
            ['id_emp' => $emp->id_emp],
            ['dias_adicionales' => round((float) $request->dias_adicionales, 2)]
        );

        return response()->json(['message' => 'Saldo actualizado correctamente']);
    }

    // ── Carga masiva de saldos de vacaciones ────────────────────────────────
    public function cargarSaldos(Request $request)
    {
        if (!$this->esAdministrador($request)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $request->validate([
            'fecha_corte' => 'required|date',
            'saldos'      => 'required|array|min:1',
            'saldos.*.cedula' => 'required|string',
            'saldos.*.saldo'  => 'required|numeric|min:0',
        ]);

        $actualizados  = 0;
        $noEncontrados = [];

        DB::beginTransaction();
        try {
            foreach ($request->saldos as $fila) {
                $emp = Empleado::where('identificacion', trim($fila['cedula']))
                    ->where('estado', 'ACTIVO')
                    ->where('id_depto', '!=', 999)
                    ->first();

                if (!$emp) {
                    $noEncontrados[] = $fila['cedula'];
                    continue;
                }

                CabeceraVacacion::updateOrCreate(
                    ['id_emp' => $emp->id_emp],
                    [
                        'dias_adicionales'    => round((float)$fila['saldo'], 2),
                        'total_dias_tomados'  => 0,
                        'total_tomados'       => 0,
                        'dias_x_tomar_normal' => round((float)$fila['saldo'], 2),
                        'fecha_proceso'       => $request->fecha_corte,
                    ]
                );

                $actualizados++;
            }

            // Actualizar fecha de corte global
            Configuracion::where('concepto', 'FECHA_CORTE_VACACIONES')
                ->update(['valor' => $request->fecha_corte]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al procesar: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'actualizados'  => $actualizados,
            'no_encontrados' => $noEncontrados,
        ]);
    }

    // ── PDF resumen (todos los empleados filtrados) ──────────────────────────
    public function pdf(Request $request)
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

        $empleados    = $query->orderBy('apellido_emp')->orderBy('nombre_emp')->get();
        $cabecerasMap = CabeceraVacacion::whereIn('id_emp', $empleados->pluck('id_emp'))
            ->get()->keyBy('id_emp');

        $filas = $empleados->map(function (Empleado $emp) use ($cabecerasMap) {
            $cabecera = $cabecerasMap->get($emp->id_emp);
            return [
                'nombre_completo' => trim($emp->apellido_emp . ' ' . $emp->nombre_emp),
                'departamento'    => $emp->departamento?->nombre_depto ?? '—',
                'tipo_contrato'   => trim($emp->tipo_contrato ?? ''),
                'tomados'         => (float)($cabecera?->total_dias_tomados ?? 0),
                'saldo_actual'    => $this->calcularSaldoActual($emp),
            ];
        });

        $nombreInst  = Configuracion::where('concepto', 'nombre_institucion')->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
        $logo        = file_exists(public_path('logo.png'))
            ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('logo.png')))
            : null;
        $generadoPor = trim($request->user()->apellido_emp . ' ' . $request->user()->nombre_emp);
        $departamento = null;
        if ($request->id_depto) {
            $departamento = \App\Models\Departamento::find($request->id_depto)?->nombre_depto;
        }

        $pdf = Pdf::loadView('reportes.vac_reporte_saldo', compact(
            'filas', 'logo', 'nombreInst', 'generadoPor', 'departamento'
        ))->setPaper('a4', 'portrait');

        return $pdf->stream('reporte_saldo_vacaciones.pdf');
    }
}
