<?php
namespace App\Http\Controllers;

use App\Models\CabeceraVacacion;
use App\Models\Configuracion;
use App\Models\Empleado;
use App\Models\LiquidacionHistorico;
use App\Models\ModalidadLaboral;
use App\Services\AuditoriaService;
use App\Services\SaldoVacacionesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class LiquidacionVacController extends Controller
{
    public function __construct(private SaldoVacacionesService $saldoService)
    {
    }

    // Motivos válidos por modalidad laboral
    // Nombramiento definitivo:  INICIO_COMISION, FIN_COMISION_RETORNO
    // Comisión de servicios:    COMISION_ENTRANTE, FIN_COMISION_SALIDA
    // Contrato ocasional / Nombramiento provisional: NUEVO_INGRESO, DESVINCULACION

    // Estado requerido por motivo: ACTIVO o INACTIVO
    private const ESTADO_REQUERIDO = [
        'INICIO_COMISION'      => 'INACTIVO',  // ya se fue de comisión
        'FIN_COMISION_RETORNO' => 'INACTIVO',  // está inactivo, regresa a la institución
        'COMISION_ENTRANTE'    => 'ACTIVO',    // ya llegó de otra institución
        'FIN_COMISION_SALIDA'  => 'INACTIVO',  // ya se fue a su institución de origen
        'DESVINCULACION'       => 'INACTIVO',  // ya salió
    ];

    // Keyeado por el `codigo` estable de d2_modalidad_laboral (ModalidadLaboral::COD_*),
    // no por el nombre — así TH puede renombrar la modalidad sin romper esto.
    private const MOTIVOS_POR_MODALIDAD = [
        ModalidadLaboral::COD_NOMBRAMIENTO_DEFINITIVO     => ['INICIO_COMISION', 'FIN_COMISION_RETORNO'],
        ModalidadLaboral::COD_CONTRATO_OCASIONAL          => ['DESVINCULACION'],
        ModalidadLaboral::COD_NOMBRAMIENTO_PROVISIONAL    => ['DESVINCULACION'],
        ModalidadLaboral::COD_LIBRE_NOMBRAMIENTO_REMOCION => ['DESVINCULACION'],
    ];

    // Motivos adicionales para empleados comisionados entrantes (independiente de la modalidad)
    private const MOTIVOS_COMISIONADO_ENTRANTE = ['COMISION_ENTRANTE', 'FIN_COMISION_SALIDA'];

    // Motivos que requieren cargar días de certificado externo
    private const MOTIVOS_CARGA_SALDO = ['FIN_COMISION_RETORNO', 'COMISION_ENTRANTE'];

    // Motivos que generan certificado PDF
    private const MOTIVOS_CON_CERTIFICADO = ['INICIO_COMISION', 'FIN_COMISION_SALIDA'];

    // ── Helper: verifica que el usuario autenticado es TH o Admin ────────────
    private function esAdminOTH($id_emp): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])
            ->exists();
    }

    // Motivos disponibles para un empleado — por su modalidad laboral, más los de
    // comisionado entrante si aplica. Un solo lugar para esta lógica: antes consultar()
    // la calculaba para mostrarla en la UI pero registrar() nunca la usaba, así que
    // ofrecía COMISION_ENTRANTE/FIN_COMISION_SALIDA en el formulario y los rechazaba con
    // 422 al guardar — el flujo de comisionado entrante estaba roto de punta a punta.
    private function motivosDisponiblesPara(Empleado $emp): array
    {
        $codigo  = ModalidadLaboral::codigoDe($emp->modalidad_laboral);
        $motivos = self::MOTIVOS_POR_MODALIDAD[$codigo] ?? [];
        if ($emp->es_comisionado_entrante) {
            $motivos = array_unique(array_merge($motivos, self::MOTIVOS_COMISIONADO_ENTRANTE));
        }
        return $motivos;
    }

    // ── Helper: calcula saldo hasta una fecha de referencia ──────────────────
    // Delegado a SaldoVacacionesService (única fuente de verdad) — antes tenía su propia
    // tasa fija 1.25 para Código del Trabajo, ignorando antigüedad (Art. 69): un servidor
    // CT con 6+ años recibía un certificado/liquidación por menos días de los que le
    // correspondían. También delega la fecha de corte efectiva (fechaCorteEfectiva() del
    // servicio), que ahora considera cabecera.fecha_proceso — antes, tras cargar un saldo
    // externo (retorno de comisión), el próximo cálculo seguía sumando desde el corte
    // global de toda la institución encima del saldo recién cargado, inflándolo.
    private function calcularSaldo(Empleado $emp, string $fechaReferencia): array
    {
        $cabecera  = CabeceraVacacion::where('id_emp', $emp->id_emp)->first();
        $fechaRef  = Carbon::parse($fechaReferencia);
        $resultado = $this->saldoService->calcular($emp, $cabecera, $fechaRef);
        $tasaInfo  = $this->saldoService->tasaVacaciones($emp, $fechaRef);

        // LiquidacionVacController NO aplica el tope de 60 (LOSEP Art. 29) — usa el valor
        // real acumulado, correcto para pago por cesación/comisión (ver CLAUDE.md).
        $saldoLiquidado = max(0, round(
            $resultado['saldo_inicial'] + $resultado['acumulado_a_hoy'] - $resultado['tomados'],
            2
        ));

        return [
            'saldo_inicial'    => $resultado['saldo_inicial'],
            'acumulado'        => $resultado['acumulado_a_hoy'],
            'tomados'          => $resultado['tomados'],
            'saldo_liquidado'  => $saldoLiquidado,
            'fecha_referencia' => $resultado['fecha_referencia'],
            'fecha_corte'      => $resultado['fecha_corte'],
            'tasa'             => $tasaInfo['tasa_mensual'],
            'tipo_contrato'    => trim($emp->tipo_contrato),
        ];
    }

    // ── Buscar empleado (activo o inactivo) ──────────────────────────────────
    public function buscar(Request $request)
    {
        if (!$this->esAdminOTH($request->user()->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $request->validate(['q' => 'required|string|min:2']);

        $empleados = Empleado::where('id_depto', '!=', 999)
            ->where(function ($query) use ($request) {
                $q = $request->q;
                $query->where('identificacion', 'ilike', "%{$q}%")
                      ->orWhereRaw("CONCAT(apellido_emp, ' ', nombre_emp) ilike ?", ["%{$q}%"]);
            })
            ->orderBy('apellido_emp')
            ->limit(15)
            ->get(['id_emp', 'identificacion', 'nombre_emp', 'apellido_emp',
                   'estado', 'modalidad_laboral', 'fecha_ingreso', 'fecha_salida',
                   'tipo_contrato', 'id_depto']);

        return response()->json($empleados);
    }

    // ── Consultar saldo de un empleado ───────────────────────────────────────
    public function consultar(Request $request, string $id_emp)
    {
        if (!$this->esAdminOTH($request->user()->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $emp = Empleado::with('departamento')
            ->where('id_emp', $id_emp)
            ->where('id_depto', '!=', 999)
            ->firstOrFail();

        // Si inactivo y tiene fecha_salida, calcular hasta esa fecha; si no, hasta hoy
        $estaInactivo = strtoupper(trim($emp->estado)) === 'INACTIVO';
        $fechaRef = ($estaInactivo && $emp->fecha_salida)
            ? Carbon::parse($emp->fecha_salida)->toDateString()
            : Carbon::today()->toDateString();

        $saldo    = $this->calcularSaldo($emp, $fechaRef);
        $historial = LiquidacionHistorico::where('id_emp', $id_emp)
            ->orderBy('fecha_evento', 'desc')
            ->get();

        return response()->json([
            'empleado' => [
                'id_emp'             => $emp->id_emp,
                'identificacion'     => $emp->identificacion,
                'nombre'             => $emp->apellido_emp . ', ' . $emp->nombre_emp,
                'estado'             => $emp->estado,
                'modalidad_laboral'  => trim($emp->modalidad_laboral ?? ''),
                'fecha_ingreso'      => $emp->fecha_ingreso,
                'fecha_salida'       => $emp->fecha_salida,
                'tipo_contrato'      => trim($emp->tipo_contrato),
                'departamento'       => $emp->departamento?->nombre_depto,
            ],
            'saldo'              => $saldo,
            'historial'          => $historial,
            'motivos_disponibles'=> $this->motivosDisponiblesPara($emp),
            'motivos_carga_saldo'=> self::MOTIVOS_CARGA_SALDO,
        ]);
    }

    // ── Registrar evento ─────────────────────────────────────────────────────
    public function registrar(Request $request, string $id_emp)
    {
        if (!$this->esAdminOTH($request->user()->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $emp = Empleado::with('departamento')
            ->where('id_emp', $id_emp)
            ->where('id_depto', '!=', 999)
            ->firstOrFail();

        // Motivos válidos para ESTE empleado (mismo criterio que consultar(), incluye
        // COMISION_ENTRANTE/FIN_COMISION_SALIDA si es_comisionado_entrante) — antes se
        // validaba contra una lista fija que nunca incluía esos dos motivos.
        $motivosValidos = $this->motivosDisponiblesPara($emp);

        $request->validate([
            'motivo'        => 'required|in:' . implode(',', $motivosValidos),
            'fecha_evento'  => 'required|date',
            'dias_a_cargar' => 'nullable|numeric|min:0',
            'observacion'   => 'nullable|string|max:500',
        ]);

        $motivo = $request->motivo;

        // Validar que el estado del empleado sea compatible con el motivo
        $estadoRequerido = self::ESTADO_REQUERIDO[$motivo] ?? null;
        if ($estadoRequerido && strtoupper($emp->estado) !== $estadoRequerido) {
            $mensajes = [
                'INACTIVO' => 'El empleado debe estar INACTIVO (con fecha de salida registrada) para registrar este evento.',
                'ACTIVO'   => 'El empleado debe estar ACTIVO para registrar este evento.',
            ];
            return response()->json(['message' => $mensajes[$estadoRequerido]], 422);
        }

        // Para motivos que requieren INACTIVO, verificar que tenga fecha_salida
        if ($estadoRequerido === 'INACTIVO' && !$emp->fecha_salida) {
            return response()->json(['message' => 'El empleado no tiene fecha de salida registrada en su ficha.'], 422);
        }

        // Calcular saldo hasta la fecha del evento
        $saldo = $this->calcularSaldo($emp, $request->fecha_evento);

        $cabeceraAnterior = null;
        $huboCorreccionCabecera = in_array($motivo, self::MOTIVOS_CARGA_SALDO, true);

        // Si el motivo requiere cargar días de certificado externo, actualizar cabecera
        if ($huboCorreccionCabecera) {
            $cabeceraPrevia   = CabeceraVacacion::where('id_emp', $emp->id_emp)->first();
            $cabeceraAnterior = $cabeceraPrevia ? [
                'dias_adicionales'   => $cabeceraPrevia->dias_adicionales,
                'total_dias_tomados' => $cabeceraPrevia->total_dias_tomados,
                'fecha_proceso'      => (string) $cabeceraPrevia->fecha_proceso,
            ] : null;

            $diasACargar = (float) ($request->dias_a_cargar ?? 0);
            CabeceraVacacion::updateOrCreate(
                ['id_emp' => $emp->id_emp],
                [
                    'dias_adicionales'   => $diasACargar,
                    'total_dias_tomados' => 0,
                    // fecha_proceso = fecha del evento (no "now()") — es la fecha real desde
                    // la que este saldo "fresco" empieza a acumular para este empleado. La lee
                    // SaldoVacacionesService::fechaCorteEfectiva() como corte por-empleado, así
                    // el próximo cálculo no vuelve a sumar todo el período desde el corte global
                    // encima del saldo recién cargado (eso era lo que inflaba el saldo).
                    'fecha_proceso'      => $request->fecha_evento,
                ]
            );
            // Recalcular saldo con los nuevos días cargados
            $saldo['saldo_inicial']  = $diasACargar;
            $saldo['tomados']        = 0;
            $saldo['saldo_liquidado'] = $diasACargar;
        }

        // Guardar fotografía del saldo en el histórico
        $historico = LiquidacionHistorico::create([
            'id_emp'            => $emp->id_emp,
            'motivo'            => $motivo,
            'fecha_evento'      => $request->fecha_evento,
            'fecha_corte_usada' => $saldo['fecha_corte'],
            'saldo_inicial'     => $saldo['saldo_inicial'],
            'acumulado'         => $saldo['acumulado'],
            'tomados'           => $saldo['tomados'],
            'saldo_liquidado'   => $saldo['saldo_liquidado'],
            'observacion'       => $request->observacion,
            'usuario_proceso'   => $request->user()->id_emp,
            'fecha_registro'    => now(),
        ]);

        AuditoriaService::log('dbo.vac_liquidacion_historico', $historico->id, 'REGISTRAR',
            $huboCorreccionCabecera ? ['cabecera_vacacion' => $cabeceraAnterior] : null,
            [
                'motivo'                    => $motivo,
                'fecha_evento'              => $request->fecha_evento,
                'saldo_liquidado'           => $saldo['saldo_liquidado'],
                'cabecera_vacacion_tocada'  => $huboCorreccionCabecera,
                'dias_cargados'             => $huboCorreccionCabecera ? (float) ($request->dias_a_cargar ?? 0) : null,
            ],
            $request, "Liquidación de vacaciones ({$motivo}): " . trim($emp->apellido_emp . ' ' . $emp->nombre_emp));

        return response()->json([
            'message'              => 'Evento registrado correctamente',
            'historico'            => $historico,
            'saldo'                => $saldo,
            'genera_certificado'   => in_array($motivo, self::MOTIVOS_CON_CERTIFICADO),
        ], 201);
    }

    // ── Generar certificado/reporte PDF ──────────────────────────────────────
    public function generarCertificado(Request $request, int $historico_id)
    {
        if (!$this->esAdminOTH($request->user()->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $historico = LiquidacionHistorico::with('empleado.departamento')->findOrFail($historico_id);

        $emp = $historico->empleado;

        $firmanteNombre = optional(Configuracion::find('FIRMANTE_TH_NOMBRE'))->valor
                          ?? optional(Configuracion::find('APROBADOR_INST_VACACION'))->valor
                          ?? 'Coordinador General Administrativo Financiero';
        $firmanteCargo  = optional(Configuracion::find('FIRMANTE_TH_CARGO'))->valor
                          ?? 'Directora de Administración del Talento Humano';
        $fechaHoy      = Carbon::now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        $usuarioActual = $request->user();
        $generadoPor   = strtoupper($usuarioActual->apellido_emp . ' ' . $usuarioActual->nombre_emp);

        $pdf = Pdf::loadView('reportes.liquidacion_vacaciones', [
            'historico'      => $historico,
            'empleado'       => $emp,
            'firmanteNombre' => strtoupper($firmanteNombre),
            'firmanteCargo'  => strtoupper($firmanteCargo),
            'fechaHoy'       => $fechaHoy,
            'generadoPor'    => $generadoPor,
        ])->setPaper('a4', 'portrait');

        $motivo   = strtolower($historico->motivo);
        $filename = "vacaciones_{$motivo}_{$emp->identificacion}_{$historico->fecha_evento}.pdf";

        return $pdf->download($filename);
    }
}
