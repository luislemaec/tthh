<?php
namespace App\Http\Controllers;

use App\Models\CabeceraVacacion;
use App\Models\Configuracion;
use App\Models\Empleado;
use App\Models\LiquidacionHistorico;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class LiquidacionVacController extends Controller
{
    private const MOTIVOS = ['DESVINCULACION', 'COMISION_SALIDA', 'COMISION_RETORNO', 'NUEVO_INGRESO'];

    // ── Helper: verifica que el usuario autenticado es TH o Admin ────────────
    private function esAdminOTH($id_emp): bool
    {
        return \Illuminate\Support\Facades\DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])
            ->exists();
    }

    // ── Helper: calcula saldo hasta una fecha de referencia ──────────────────
    private function calcularSaldo(Empleado $emp, string $fechaReferencia): array
    {
        $tasas = [
            'LOSEP'              => 2.50,
            'CODIGO DEL TRABAJO' => 1.25,
        ];
        $tasa = $tasas[trim($emp->tipo_contrato)] ?? 0;

        $fechaCorteConfig = Configuracion::find('FECHA_CORTE_VACACIONES');
        $fechaCorte       = $fechaCorteConfig ? Carbon::parse($fechaCorteConfig->valor) : Carbon::today();

        if ($emp->fecha_ingreso && Carbon::parse($emp->fecha_ingreso)->gt($fechaCorte)) {
            $fechaCorte = Carbon::parse($emp->fecha_ingreso);
        }

        $fechaRef       = Carbon::parse($fechaReferencia);
        $diasCalendario = max(0, $fechaCorte->diffInDays($fechaRef));
        $acumulado      = round($diasCalendario / 360 * ($tasa * 12), 2);

        $cabecera     = CabeceraVacacion::where('id_emp', $emp->id_emp)->first();
        $saldoInicial = (float) ($cabecera?->dias_adicionales  ?? 0);
        $tomados      = (float) ($cabecera?->total_dias_tomados ?? 0);
        $disponibles  = round($saldoInicial + $acumulado - $tomados, 2);

        return [
            'saldo_inicial'    => $saldoInicial,
            'acumulado'        => $acumulado,
            'tomados'          => $tomados,
            'saldo_liquidado'  => max(0, $disponibles),
            'fecha_referencia' => $fechaRef->toDateString(),
            'fecha_corte'      => $fechaCorte->toDateString(),
            'tasa'             => $tasa,
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
            ->get(['id_emp', 'identificacion', 'nombre_emp', 'apellido_emp', 'estado', 'motivo_inactividad', 'fecha_ingreso', 'fecha_salida', 'tipo_contrato', 'id_depto']);

        return response()->json($empleados);
    }

    // ── Consultar saldo de un empleado (hasta fecha_salida si inactivo) ──────
    public function consultar(Request $request, string $id_emp)
    {
        if (!$this->esAdminOTH($request->user()->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $emp = Empleado::where('id_emp', $id_emp)
            ->where('id_depto', '!=', 999)
            ->firstOrFail();

        // Si inactivo, calcular hasta fecha_salida; si activo, hasta hoy
        $fechaRef = (strtoupper($emp->estado) !== 'ACTIVO' && $emp->fecha_salida)
            ? $emp->fecha_salida
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
                'motivo_inactividad' => $emp->motivo_inactividad,
                'fecha_ingreso'      => $emp->fecha_ingreso,
                'fecha_salida'       => $emp->fecha_salida,
                'tipo_contrato'      => trim($emp->tipo_contrato),
            ],
            'saldo'    => $saldo,
            'historial' => $historial,
        ]);
    }

    // ── Registrar evento (guarda histórico y actualiza motivo) ───────────────
    public function registrar(Request $request, string $id_emp)
    {
        if (!$this->esAdminOTH($request->user()->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $request->validate([
            'motivo'      => 'required|in:DESVINCULACION,COMISION_SALIDA,COMISION_RETORNO,NUEVO_INGRESO',
            'fecha_evento' => 'required|date',
            'observacion'  => 'nullable|string|max:500',
        ]);

        $emp = Empleado::where('id_emp', $id_emp)
            ->where('id_depto', '!=', 999)
            ->firstOrFail();

        $saldo = $this->calcularSaldo($emp, $request->fecha_evento);

        // Guardar fotografía del saldo
        $historico = LiquidacionHistorico::create([
            'id_emp'           => $emp->id_emp,
            'motivo'           => $request->motivo,
            'fecha_evento'     => $request->fecha_evento,
            'fecha_corte_usada'=> $saldo['fecha_corte'],
            'saldo_inicial'    => $saldo['saldo_inicial'],
            'acumulado'        => $saldo['acumulado'],
            'tomados'          => $saldo['tomados'],
            'saldo_liquidado'  => $saldo['saldo_liquidado'],
            'observacion'      => $request->observacion,
            'usuario_proceso'  => $request->user()->id_emp,
            'fecha_registro'   => now(),
        ]);

        // Actualizar motivo_inactividad en el empleado
        $emp->update(['motivo_inactividad' => $request->motivo]);

        return response()->json([
            'message'   => 'Evento registrado correctamente',
            'historico' => $historico,
            'saldo'     => $saldo,
        ], 201);
    }

    // ── Generar certificado/reporte PDF ──────────────────────────────────────
    public function generarCertificado(Request $request, int $historico_id)
    {
        if (!$this->esAdminOTH($request->user()->id_emp)) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $historico = LiquidacionHistorico::with('empleado.departamento')->findOrFail($historico_id);
        $emp       = $historico->empleado;

        $aprobador = optional(Configuracion::find('APROBADOR_INST_VACACION'))->valor
                     ?? 'Coordinador General Administrativo Financiero';
        $fechaHoy  = Carbon::now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        $pdf = Pdf::loadView('reportes.liquidacion_vacaciones', [
            'historico' => $historico,
            'empleado'  => $emp,
            'aprobador' => $aprobador,
            'fechaHoy'  => $fechaHoy,
        ])->setPaper('a4', 'portrait');

        $motivo   = strtolower($historico->motivo);
        $filename = "vacaciones_{$motivo}_{$emp->identificacion}_{$historico->fecha_evento}.pdf";

        return $pdf->download($filename);
    }
}
