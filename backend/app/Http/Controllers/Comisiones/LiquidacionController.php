<?php

namespace App\Http\Controllers\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\ComFichaLiquidacion;
use App\Models\ComSolicitud;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiquidacionController extends Controller
{
    public function store(Request $request, int $solicitudId): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($solicitudId);

        if ($solicitud->estado !== 'EN_PAGO') {
            return response()->json(['message' => 'La solicitud debe estar EN_PAGO'], 422);
        }
        if (!$this->tieneRol($request, 'CONTABILIDAD') && !$this->tieneRol($request, 'ADMINISTRADOR')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        if ($solicitud->fichaLiquidacion) {
            return response()->json(['message' => 'Ya existe una ficha de liquidación'], 422);
        }

        $request->validate([
            'dias_viaticos'       => 'required|integer|min:0',
            'anticipo_viatico'    => 'nullable|numeric|min:0',
            'anticipo_combustible'=> 'nullable|numeric|min:0',
            'justif_alimentacion' => 'nullable|numeric|min:0',
            'justif_alojamiento'  => 'nullable|numeric|min:0',
            'movilizacion'        => 'nullable|numeric|min:0',
            'peajes_parqueaderos' => 'nullable|numeric|min:0',
            'combustibles'        => 'nullable|numeric|min:0',
            'otros_gastos'        => 'nullable|numeric|min:0',
            // Exterior
            'pais_destino'        => 'nullable|string|max:100',
            'coeficiente_pais'    => 'nullable|numeric|min:0',
        ]);

        // Auto-calcular valor_por_dia según tipo y grupo ocupacional
        $valorDia = $this->calcularValorDia($solicitud, $request->all());

        $fichaData = $this->calcular(array_merge($request->all(), ['valor_por_dia' => $valorDia]), $solicitud->tipo);

        $ficha = ComFichaLiquidacion::create([
            'solicitud_id'           => $solicitudId,
            ...$fichaData,
            'pais_destino'           => $request->pais_destino,
            'coeficiente_pais'       => $request->coeficiente_pais,
            'estado'                 => 'BORRADOR',
            'created_by'             => $request->user()->id_emp,
            'updated_by'             => $request->user()->id_emp,
        ]);

        $solicitud->update(['estado' => 'EN_LIQUIDACION', 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.com_ficha_liquidacion', $ficha->id, 'CREAR', null, ['total_a_pagar' => $ficha->total_a_pagar, 'tipo_resultado' => $ficha->tipo_resultado], $request, 'Ficha de liquidación creada');

        return response()->json($ficha, 201);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $ficha     = ComFichaLiquidacion::findOrFail($id);
        $solicitud = ComSolicitud::findOrFail($ficha->solicitud_id);

        if (!in_array($ficha->estado, ['BORRADOR', 'EN_REVISION'])) {
            return response()->json(['message' => 'No se puede editar en este estado'], 422);
        }
        if (!$this->tieneRol($request, 'CONTABILIDAD') && !$this->tieneRol($request, 'ADMINISTRADOR')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $fichaData = $this->calcular($request->all(), $solicitud->tipo);
        $ficha->update([...$fichaData, 'updated_by' => $request->user()->id_emp]);

        return response()->json($ficha);
    }

    public function registrarCurCompromiso(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        return $this->registrarCur($request, $id, 'cur_compromiso', 'PRESUPUESTO', 'CUR_COMPROMISO', 'CUR de compromiso registrado');
    }

    public function registrarCurDevengado(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        return $this->registrarCur($request, $id, 'cur_devengado', 'CONTABILIDAD', 'CUR_DEVENGADO', 'CUR de devengado registrado');
    }

    public function confirmarPago(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $ficha     = ComFichaLiquidacion::findOrFail($id);
        $solicitud = ComSolicitud::findOrFail($ficha->solicitud_id);

        if (!$this->tieneRol($request, 'TESORERIA') && !$this->tieneRol($request, 'ADMINISTRADOR')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $anterior = $ficha->estado;

        // Si es POR_COBRAR el empleado debe devolver dinero — pasar a ese estado
        if ($ficha->tipo_resultado === 'POR_COBRAR') {
            $ficha->update(['estado' => 'POR_COBRAR', 'updated_by' => $request->user()->id_emp]);
            $solicitud->update(['estado' => 'POR_COBRAR', 'updated_by' => $request->user()->id_emp]);
        } else {
            $ficha->update(['estado' => 'PAGADO', 'updated_by' => $request->user()->id_emp]);
            $solicitud->update(['estado' => 'CERRADO', 'updated_by' => $request->user()->id_emp]);
        }

        AuditoriaService::log('dbo.com_ficha_liquidacion', $id, 'CONFIRMAR_PAGO', ['estado' => $anterior], ['estado' => $ficha->estado], $request, 'Pago de viáticos procesado por Tesorería');

        return response()->json(['estado' => $ficha->estado, 'solicitud_estado' => $solicitud->estado]);
    }

    public function registrarDevolucion(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $ficha     = ComFichaLiquidacion::findOrFail($id);
        $solicitud = ComSolicitud::findOrFail($ficha->solicitud_id);

        if ($ficha->tipo_resultado !== 'POR_COBRAR') {
            return response()->json(['message' => 'Esta ficha no es de tipo POR_COBRAR'], 422);
        }
        if (!$this->tieneRol($request, 'TESORERIA') && !$this->tieneRol($request, 'ADMINISTRADOR')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'comprobante_devolucion' => 'required|string|max:100',
            'fecha_devolucion'       => 'required|date',
        ]);

        $ficha->update([
            'comprobante_devolucion' => $request->comprobante_devolucion,
            'fecha_devolucion'       => $request->fecha_devolucion,
            'estado'                 => 'PAGADO',
            'updated_by'             => $request->user()->id_emp,
        ]);
        $solicitud->update(['estado' => 'CERRADO', 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.com_ficha_liquidacion', $id, 'REGISTRAR_DEVOLUCION', ['estado' => 'POR_COBRAR'], ['estado' => 'PAGADO', 'comprobante' => $request->comprobante_devolucion], $request, 'Devolución de anticipo registrada por Tesorería');

        return response()->json(['estado' => $ficha->estado, 'solicitud_estado' => $solicitud->estado]);
    }

    public function pdf(\Illuminate\Http\Request $request, int $id)
    {
        $solicitud = ComSolicitud::with(['empleado', 'fichaLiquidacion'])->findOrFail($id);
        $ficha     = $solicitud->fichaLiquidacion;

        if (!$ficha) {
            abort(404, 'Ficha de liquidación no encontrada');
        }

        $logo        = file_exists(public_path('logo.png')) ? base64_encode(file_get_contents(public_path('logo.png'))) : null;
        $nombreInst  = 'CONSEJO DE COMUNICACIÓN';
        $generadoPor = trim($solicitud->empleado->apellido_emp ?? '') . ' ' . trim($solicitud->empleado->nombre_emp ?? '');

        $template = $solicitud->tipo === 'EXTERIOR'
            ? 'reportes.com_ficha_exterior'
            : 'reportes.com_ficha_interior';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($template, compact(
            'solicitud', 'ficha', 'logo', 'nombreInst', 'generadoPor'
        ))->setPaper('a4', 'portrait');

        $numero = $solicitud->numero_solicitud ?? ('COM-' . $id);
        return $pdf->stream("ficha-liquidacion-{$numero}.pdf");
    }

    public function coeficientes(): \Illuminate\Http\JsonResponse
    {
        $paises = DB::table('dbo.com_coeficiente_pais')
            ->where('activo', true)
            ->orderBy('region')
            ->orderBy('pais')
            ->get();
        return response()->json($paises);
    }

    private function calcularValorDia(ComSolicitud $solicitud, array $data): float
    {
        if ($solicitud->tipo === 'EXTERIOR') {
            // Exterior: base * coeficiente
            $base        = (float)(DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'valor_base_exterior'")->value('valor') ?? 185.00);
            $coeficiente = (float)($data['coeficiente_pais'] ?? 1.0);
            return round($base * $coeficiente, 2);
        }

        // Interior: tarifa según grupo ocupacional
        $grupo = strtoupper(DB::table('dbo.ad_empleado')->where('id_emp', $solicitud->id_emp)->value('grupo_ocupacional') ?? '');
        $esJerarquico = str_contains($grupo, 'JERARQUICO') || str_contains($grupo, 'JERÁRQUICO');

        $tarifa = DB::table('dbo.com_tarifa_viatico')
            ->where('activo', true)
            ->where('tipo', 'INTERIOR')
            ->where('aplica_jerarquico', $esJerarquico)
            ->value('valor_dia');

        return (float)($tarifa ?? ($esJerarquico ? 130.00 : 80.00));
    }

    private function calcular(array $data, string $tipo): array
    {
        $valorDia   = (float)($data['valor_por_dia'] ?? 0);
        $dias       = (int)($data['dias_viaticos'] ?? 0);
        $totalViatico = round($valorDia * $dias, 2);

        $anticipo_viatico    = round((float)($data['anticipo_viatico'] ?? 0), 2);
        $anticipo_combustible = $tipo === 'INTERIOR' ? round((float)($data['anticipo_combustible'] ?? 0), 2) : null;
        $totalAnticipo = $anticipo_viatico + ($anticipo_combustible ?? 0);

        $justif_alimentacion = round((float)($data['justif_alimentacion'] ?? 0), 2);
        $justif_alojamiento  = round((float)($data['justif_alojamiento'] ?? 0), 2);
        $totalJustif = $justif_alimentacion + $justif_alojamiento;

        $movilizacion       = round((float)($data['movilizacion'] ?? 0), 2);
        $peajes             = round((float)($data['peajes_parqueaderos'] ?? 0), 2);
        $combustibles       = $tipo === 'INTERIOR' ? round((float)($data['combustibles'] ?? 0), 2) : null;
        $otrosGastos        = round((float)($data['otros_gastos'] ?? 0), 2);

        $viaticoPorPagar     = round($totalViatico - $totalAnticipo, 2);
        $devolucionMovilizacion = round($movilizacion + $peajes + ($combustibles ?? 0), 2);
        $totalAPagar         = round($viaticoPorPagar + $devolucionMovilizacion + $otrosGastos, 2);
        $tipoResultado       = $totalAPagar >= 0 ? 'POR_PAGAR' : 'POR_COBRAR';

        return [
            'valor_por_dia'          => $valorDia,
            'dias_viaticos'          => $dias,
            'total_viatico'          => $totalViatico,
            'anticipo_viatico'       => $anticipo_viatico,
            'anticipo_combustible'   => $anticipo_combustible,
            'total_anticipo'         => round($totalAnticipo, 2),
            'justif_alimentacion'    => $justif_alimentacion,
            'justif_alojamiento'     => $justif_alojamiento,
            'total_justificacion'    => round($totalJustif, 2),
            'movilizacion'           => $movilizacion,
            'peajes_parqueaderos'    => $peajes,
            'combustibles'           => $combustibles,
            'otros_gastos'           => $otrosGastos,
            'viaticos_por_pagar'     => $viaticoPorPagar,
            'devolucion_movilizacion'=> $devolucionMovilizacion,
            'total_a_pagar'          => $totalAPagar,
            'tipo_resultado'         => $tipoResultado,
        ];
    }

    private function registrarCur(Request $request, int $id, string $campo, string $rol, string $accion, string $desc): \Illuminate\Http\JsonResponse
    {
        $ficha = ComFichaLiquidacion::findOrFail($id);

        if (!$this->tieneRol($request, $rol) && !$this->tieneRol($request, 'ADMINISTRADOR') && !$this->tieneRol($request, 'DIRECTOR FINANCIERO')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([$campo => 'required|string|max:50']);
        $ficha->update([$campo => $request->$campo, 'updated_by' => $request->user()->id_emp]);
        AuditoriaService::log('dbo.com_ficha_liquidacion', $id, $accion, [], [$campo => $request->$campo], $request, $desc);

        return response()->json(['message' => 'Registrado', $campo => $request->$campo]);
    }

    private function tieneRol(Request $request, string $rol): bool
    {
        $idEmp = $request->user()->id_emp;
        return DB::table('dbo.admin_usuario_rol')
            ->join('dbo.admin_rol', 'admin_usuario_rol.rol_id', '=', 'admin_rol.id')
            ->where('admin_usuario_rol.id_emp', $idEmp)
            ->where('admin_rol.nombre', $rol)
            ->exists();
    }
}
