<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\AuditoriaService;

class RolPagoController extends Controller
{
    private const ROLES_NOMINA = ['ADMINISTRADOR', 'TH NOMINA'];

    private function calcularDiasEnMes(?string $fechaIngreso, int $anio, int $mes): int
    {
        if (!$fechaIngreso) return 30;

        $primerDia = Carbon::createFromDate($anio, $mes, 1)->startOfDay();
        $ultimoDia = Carbon::createFromDate($anio, $mes, 1)->endOfMonth()->startOfDay();
        $ingreso   = Carbon::parse($fechaIngreso)->startOfDay();

        if ($ingreso->gt($ultimoDia)) return 0;
        if ($ingreso->lt($primerDia)) return 30;

        $diaIngreso = (int)$ingreso->format('d');
        return max(1, 30 - $diaIngreso + 1);
    }

    private function logoBase64(): ?string
    {
        $path = public_path('logo.png');
        return file_exists($path)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($path))
            : null;
    }

    private function nombreMes(int $mes): string
    {
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return $meses[$mes] ?? '';
    }

    private function tasasVigentes(): \Illuminate\Support\Collection
    {
        return DB::table('dbo.d2_aportes_iess')
            ->where(function ($q) {
                $q->whereNull('fecha_hasta')->orWhere('fecha_hasta', '>=', now()->toDateString());
            })
            ->orderByDesc('fecha_desde')
            ->get()
            ->unique('modalidad')
            ->keyBy(fn($r) => trim($r->modalidad));
    }

    private function detallesConEmpleado(int $cabId): \Illuminate\Support\Collection
    {
        return DB::table('dbo.nom_rol_pago_det as d')
            ->join('dbo.ad_empleado as e', 'd.id_emp', '=', 'e.id_emp')
            ->join('dbo.ad_departamento as dep', 'e.id_depto', '=', 'dep.id_depto')
            ->where('d.cab_id', $cabId)
            ->select(
                'd.*',
                'e.nombre_emp', 'e.apellido_emp', 'e.identificacion',
                'e.grupo_ocupacional as cargo',
                'dep.nombre_depto'
            )
            ->orderBy('e.apellido_emp')
            ->orderBy('e.nombre_emp')
            ->get();
    }

    private function recalcularTotalesCab(int $cabId): void
    {
        $t = DB::table('dbo.nom_rol_pago_det')
            ->where('cab_id', $cabId)
            ->selectRaw('COUNT(*) as cnt,
                         COALESCE(SUM(valor_rmu),0) as bruto,
                         COALESCE(SUM(aporte_patronal + iece + secap),0) as patronal,
                         COALESCE(SUM(total_descuentos),0) as desc_total,
                         COALESCE(SUM(liquido),0) as liq')
            ->first();

        DB::table('dbo.nom_rol_pago_cab')->where('id', $cabId)->update([
            'total_empleados'  => $t->cnt,
            'total_bruto'      => $t->bruto,
            'total_patronal'   => $t->patronal,
            'total_descuentos' => $t->desc_total,
            'total_liquido'    => $t->liq,
            'updated_at'       => now(),
        ]);
    }

    // GET /api/nomina/rol-pago
    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $cab = DB::table('dbo.nom_rol_pago_cab')
            ->where('anio', $request->anio)
            ->where('mes', $request->mes)
            ->first();

        if (!$cab) {
            return response()->json(['cab' => null, 'detalles' => []]);
        }

        return response()->json([
            'cab'      => $cab,
            'detalles' => $this->detallesConEmpleado($cab->id),
        ]);
    }

    // POST /api/nomina/rol-pago/calcular
    public function calcular(Request $request)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $emp  = $request->user();
        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        $existeCerrado = DB::table('dbo.nom_rol_pago_cab')
            ->where('anio', $anio)->where('mes', $mes)->where('estado', 'CERRADO')
            ->exists();
        if ($existeCerrado) {
            return response()->json(['message' => 'El período ya está cerrado, no se puede recalcular.'], 422);
        }

        $tasas = $this->tasasVigentes();

        $empleados = DB::table('dbo.ad_empleado as e')
            ->join('dbo.ad_departamento as dep', 'e.id_depto', '=', 'dep.id_depto')
            ->where('e.estado', 'ACTIVO')
            ->where('e.id_depto', '<>', 999)
            ->select(
                'e.id_emp', 'e.nombre_emp', 'e.apellido_emp', 'e.identificacion',
                'e.sueldo', 'e.tipo_contrato', 'e.fecha_ingreso',
                'e.grupo_ocupacional as cargo', 'dep.nombre_depto',
                'e.programa', 'e.actividad'
            )
            ->get();

        // Empleados cuyo tipo_contrato no calzó con ninguna fila vigente de d2_aportes_iess —
        // antes esto pasaba en silencio (aportes en 0%, líquido = RMU completo, sin ningún aviso
        // ni en la respuesta ni en el PDF). Se recolectan para devolverlos en la respuesta y en
        // la auditoría, para que TH NOMINA pueda corregir el catálogo o el dato del empleado
        // antes de cerrar el período.
        $sinTasa = [];

        // Todo el cálculo (borrar el BORRADOR anterior si existía + insertar cabecera y detalle)
        // en una sola transacción — antes, si el proceso se cortaba a mitad del loop (timeout,
        // caída de conexión), quedaba un rol de pagos a medias sin forma de saberlo, y la
        // cabecera ya insertada bloqueaba un reintento limpio.
        DB::transaction(function () use ($anio, $mes, $emp, $tasas, $empleados, &$sinTasa, &$cabId) {
            $cabVieja = DB::table('dbo.nom_rol_pago_cab')
                ->where('anio', $anio)->where('mes', $mes)->first();
            if ($cabVieja) {
                DB::table('dbo.nom_rol_pago_det')->where('cab_id', $cabVieja->id)->delete();
                DB::table('dbo.nom_rol_pago_cab')->where('id', $cabVieja->id)->delete();
            }

            $cabId = DB::table('dbo.nom_rol_pago_cab')->insertGetId([
                'anio'             => $anio,
                'mes'              => $mes,
                'estado'           => 'BORRADOR',
                'total_empleados'  => 0,
                'total_bruto'      => 0,
                'total_patronal'   => 0,
                'total_descuentos' => 0,
                'total_liquido'    => 0,
                'creado_por'       => $emp->id_emp,
                'fecha_calculo'    => now(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($empleados as $e) {
                $dias = $this->calcularDiasEnMes($e->fecha_ingreso, $anio, $mes);
                if ($dias === 0) continue;

                $valorRmu = round($e->sueldo * $dias / 30, 2);

                $modalidad   = trim($e->tipo_contrato ?? '');
                $tasa        = $tasas->get($modalidad);
                if (!$tasa) {
                    $sinTasa[] = [
                        'id_emp'        => $e->id_emp,
                        'nombre'        => trim($e->apellido_emp . ' ' . $e->nombre_emp),
                        'tipo_contrato' => $e->tipo_contrato,
                    ];
                }
                $patronalPct = $tasa ? (float)$tasa->aporte_patronal  : 0;
                $personalPct = $tasa ? (float)$tasa->aporte_individual : 0;
                $iecePct     = $tasa ? (float)$tasa->iece_patronal     : 0;
                $secapPct    = $tasa ? (float)$tasa->secap_patronal    : 0;

                $aporte_patronal = round($valorRmu * $patronalPct / 100, 2);
                $aporte_personal = round($valorRmu * $personalPct / 100, 2);
                $iece            = round($valorRmu * $iecePct     / 100, 2);
                $secap           = round($valorRmu * $secapPct    / 100, 2);

                $total_descuentos = $aporte_personal;
                $liquido          = round($valorRmu - $total_descuentos, 2);

                DB::table('dbo.nom_rol_pago_det')->insert([
                    'cab_id'              => $cabId,
                    'id_emp'              => $e->id_emp,
                    'tipo_contrato'       => $e->tipo_contrato,
                    'rmu_puesto'          => $e->sueldo,
                    'dias'                => $dias,
                    'valor_rmu'           => $valorRmu,
                    'aporte_patronal_pct' => $patronalPct,
                    'aporte_patronal'     => $aporte_patronal,
                    'aporte_personal_pct' => $personalPct,
                    'aporte_personal'     => $aporte_personal,
                    'iece_pct'            => $iecePct,
                    'iece'                => $iece,
                    'secap_pct'           => $secapPct,
                    'secap'               => $secap,
                    'quirografario'       => 0,
                    'hipotecario'         => 0,
                    'impuesto_renta'      => 0,
                    'supa'                => 0,
                    'poliza_blanket'      => 0,
                    'sanciones'           => 0,
                    'otros_descuentos'    => 0,
                    'observaciones'       => null,
                    'total_descuentos'    => $total_descuentos,
                    'liquido'             => $liquido,
                    'programa'            => $e->programa,
                    'actividad'           => $e->actividad,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            $this->recalcularTotalesCab($cabId);
        });

        $cab      = DB::table('dbo.nom_rol_pago_cab')->where('id', $cabId)->first();
        $detalles = $this->detallesConEmpleado($cabId);

        AuditoriaService::log('dbo.nom_rol_pago_cab', $cabId, 'CALCULAR', null,
            ['anio' => $anio, 'mes' => $mes, 'total_empleados' => $cab->total_empleados, 'sin_tasa' => count($sinTasa)],
            $request, "Cálculo rol de pagos {$this->nombreMes($mes)} {$anio}" . (count($sinTasa) ? ' — ' . count($sinTasa) . ' empleado(s) sin tasa IESS' : ''));

        return response()->json(['cab' => $cab, 'detalles' => $detalles, 'sin_tasa' => $sinTasa]);
    }

    // PUT /api/nomina/rol-pago/detalle/{id}
    public function updateDetalle(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $request->validate([
            'quirografario'   => 'nullable|numeric|min:0',
            'hipotecario'     => 'nullable|numeric|min:0',
            'impuesto_renta'  => 'nullable|numeric|min:0',
            'supa'            => 'nullable|numeric|min:0',
            'poliza_blanket'  => 'nullable|numeric|min:0',
            'sanciones'       => 'nullable|numeric|min:0',
            'otros_descuentos'=> 'nullable|numeric|min:0',
            'observaciones'   => 'nullable|string|max:300',
        ]);

        $det = DB::table('dbo.nom_rol_pago_det')->where('id', $id)->first();
        if (!$det) return response()->json(['message' => 'Detalle no encontrado.'], 404);

        $cab = DB::table('dbo.nom_rol_pago_cab')->where('id', $det->cab_id)->first();
        if ($cab->estado !== 'BORRADOR') {
            return response()->json(['message' => 'El período está cerrado y no puede modificarse.'], 422);
        }

        $quirografario  = (float)($request->input('quirografario',  $det->quirografario)  ?? 0);
        $hipotecario    = (float)($request->input('hipotecario',    $det->hipotecario)    ?? 0);
        $impuesto_renta = (float)($request->input('impuesto_renta', $det->impuesto_renta) ?? 0);
        $supa           = (float)($request->input('supa',           $det->supa)           ?? 0);
        $poliza_blanket = (float)($request->input('poliza_blanket', $det->poliza_blanket) ?? 0);
        $sanciones      = (float)($request->input('sanciones',      $det->sanciones)      ?? 0);
        $otros_desc     = (float)($request->input('otros_descuentos', $det->otros_descuentos) ?? 0);
        $observaciones  = $request->input('observaciones', $det->observaciones);

        $total_descuentos = round(
            $det->aporte_personal + $quirografario + $hipotecario + $impuesto_renta + $supa + $poliza_blanket + $sanciones + $otros_desc,
            2
        );
        $liquido = round($det->valor_rmu - $total_descuentos, 2);

        DB::table('dbo.nom_rol_pago_det')->where('id', $id)->update([
            'quirografario'   => $quirografario,
            'hipotecario'     => $hipotecario,
            'impuesto_renta'  => $impuesto_renta,
            'supa'            => $supa,
            'poliza_blanket'  => $poliza_blanket,
            'sanciones'       => $sanciones,
            'otros_descuentos'=> $otros_desc,
            'observaciones'   => $observaciones,
            'total_descuentos'=> $total_descuentos,
            'liquido'         => $liquido,
            'updated_at'      => now(),
        ]);

        $this->recalcularTotalesCab($det->cab_id);

        // Ediciones manuales de descuentos (sanciones, impuesto renta, etc.) sobre el artefacto
        // más sensible del módulo (líquido a pagar) no dejaban ninguna traza — corregido.
        AuditoriaService::log('dbo.nom_rol_pago_det', $id, 'ACTUALIZAR_DETALLE',
            [
                'quirografario' => $det->quirografario, 'hipotecario' => $det->hipotecario,
                'impuesto_renta' => $det->impuesto_renta, 'supa' => $det->supa,
                'poliza_blanket' => $det->poliza_blanket, 'sanciones' => $det->sanciones,
                'otros_descuentos' => $det->otros_descuentos, 'liquido' => $det->liquido,
            ],
            [
                'quirografario' => $quirografario, 'hipotecario' => $hipotecario,
                'impuesto_renta' => $impuesto_renta, 'supa' => $supa,
                'poliza_blanket' => $poliza_blanket, 'sanciones' => $sanciones,
                'otros_descuentos' => $otros_desc, 'liquido' => $liquido,
            ],
            $request, "Edición manual de descuentos, id_emp {$det->id_emp}, rol de pagos cab_id {$det->cab_id}");

        return response()->json(DB::table('dbo.nom_rol_pago_det')->where('id', $id)->first());
    }

    // POST /api/nomina/rol-pago/cerrar
    public function cerrar(Request $request)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $emp = $request->user();

        $cab = DB::table('dbo.nom_rol_pago_cab')
            ->where('anio', $request->anio)
            ->where('mes', $request->mes)
            ->where('estado', 'BORRADOR')
            ->first();

        if (!$cab) {
            return response()->json(['message' => 'No existe un período en BORRADOR para cerrar.'], 422);
        }

        DB::table('dbo.nom_rol_pago_cab')->where('id', $cab->id)->update([
            'estado'       => 'CERRADO',
            'cerrado_por'  => $emp->id_emp,
            'fecha_cierre' => now(),
            'updated_at'   => now(),
        ]);

        AuditoriaService::log('dbo.nom_rol_pago_cab', $cab->id, 'CERRAR',
            ['estado' => 'BORRADOR'], ['estado' => 'CERRADO', 'total_liquido' => $cab->total_liquido],
            $request, "Cierre rol de pagos {$this->nombreMes((int)$request->mes)} {$request->anio}");

        return response()->json(DB::table('dbo.nom_rol_pago_cab')->where('id', $cab->id)->first());
    }

    // POST /api/nomina/rol-pago/reabrir — vuelve un período CERRADO a BORRADOR
    // No existía ningún flujo de reapertura: una vez CERRADO, el único remedio ante un error
    // detectado después del cierre era corregirlo a mano en la BD. Restringido a los mismos
    // roles que pueden cerrar; exige justificación y queda auditado (sin columnas nuevas — la
    // traza vive en nom_auditoria_log, igual que el resto del módulo).
    public function reabrir(Request $request)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $request->validate([
            'anio'        => 'required|integer',
            'mes'         => 'required|integer|min:1|max:12',
            'observacion' => 'required|string|max:300',
        ]);

        $cab = DB::table('dbo.nom_rol_pago_cab')
            ->where('anio', $request->anio)
            ->where('mes', $request->mes)
            ->where('estado', 'CERRADO')
            ->first();

        if (!$cab) {
            return response()->json(['message' => 'No existe un período CERRADO para este mes/año.'], 422);
        }

        DB::table('dbo.nom_rol_pago_cab')->where('id', $cab->id)->update([
            'estado'     => 'BORRADOR',
            'updated_at' => now(),
        ]);

        AuditoriaService::log('dbo.nom_rol_pago_cab', $cab->id, 'REABRIR',
            ['estado' => 'CERRADO', 'cerrado_por' => $cab->cerrado_por, 'fecha_cierre' => $cab->fecha_cierre],
            ['estado' => 'BORRADOR', 'observacion' => $request->observacion],
            $request, "Reapertura rol de pagos {$this->nombreMes((int)$request->mes)} {$request->anio}: {$request->observacion}");

        return response()->json(DB::table('dbo.nom_rol_pago_cab')->where('id', $cab->id)->first());
    }

    // POST /api/nomina/rol-pago/importar
    public function importar(Request $request)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $request->validate([
            'anio'  => 'required|integer',
            'mes'   => 'required|integer|min:1|max:12',
            'filas' => 'required|array|min:1',
            'filas.*.cedula'          => 'required|string',
            'filas.*.quirografario'   => 'nullable|numeric|min:0',
            'filas.*.hipotecario'     => 'nullable|numeric|min:0',
            'filas.*.impuesto_renta'  => 'nullable|numeric|min:0',
            'filas.*.poliza_blanket'  => 'nullable|numeric|min:0',
            'filas.*.sanciones'       => 'nullable|numeric|min:0',
            'filas.*.otros_descuentos'=> 'nullable|numeric|min:0',
        ]);

        $cab = DB::table('dbo.nom_rol_pago_cab')
            ->where('anio', $request->anio)
            ->where('mes', $request->mes)
            ->where('estado', 'BORRADOR')
            ->first();

        if (!$cab) {
            return response()->json(['message' => 'No existe un período en BORRADOR para este mes/año.'], 422);
        }

        $actualizados  = [];
        $noEncontrados = [];

        foreach ($request->filas as $fila) {
            $cedula = trim($fila['cedula']);

            $emp = DB::table('dbo.ad_empleado')
                ->where('identificacion', $cedula)
                ->value('id_emp');

            if (!$emp) { $noEncontrados[] = $cedula; continue; }

            $det = DB::table('dbo.nom_rol_pago_det')
                ->where('cab_id', $cab->id)
                ->where('id_emp', $emp)
                ->first();

            if (!$det) { $noEncontrados[] = $cedula; continue; }

            $quirografario  = isset($fila['quirografario'])  && $fila['quirografario']  !== null ? (float)$fila['quirografario']  : (float)$det->quirografario;
            $hipotecario    = isset($fila['hipotecario'])    && $fila['hipotecario']    !== null ? (float)$fila['hipotecario']    : (float)$det->hipotecario;
            $impuesto_renta = isset($fila['impuesto_renta']) && $fila['impuesto_renta'] !== null ? (float)$fila['impuesto_renta'] : (float)$det->impuesto_renta;
            $poliza_blanket = isset($fila['poliza_blanket']) && $fila['poliza_blanket'] !== null ? (float)$fila['poliza_blanket'] : (float)$det->poliza_blanket;
            $sanciones      = isset($fila['sanciones'])      && $fila['sanciones']      !== null ? (float)$fila['sanciones']      : (float)$det->sanciones;
            $otros_desc     = isset($fila['otros_descuentos'])&& $fila['otros_descuentos']!== null ? (float)$fila['otros_descuentos']: (float)$det->otros_descuentos;

            $total_descuentos = round(
                $det->aporte_personal + $quirografario + $hipotecario + $impuesto_renta + $det->supa + $poliza_blanket + $sanciones + $otros_desc,
                2
            );
            $liquido = round($det->valor_rmu - $total_descuentos, 2);

            DB::table('dbo.nom_rol_pago_det')->where('id', $det->id)->update([
                'quirografario'   => $quirografario,
                'hipotecario'     => $hipotecario,
                'impuesto_renta'  => $impuesto_renta,
                'poliza_blanket'  => $poliza_blanket,
                'sanciones'       => $sanciones,
                'otros_descuentos'=> $otros_desc,
                'total_descuentos'=> $total_descuentos,
                'liquido'         => $liquido,
                'updated_at'      => now(),
            ]);

            $actualizados[] = $cedula;
        }

        $this->recalcularTotalesCab($cab->id);

        AuditoriaService::log('dbo.nom_rol_pago_cab', $cab->id, 'IMPORTAR', null,
            ['actualizados' => count($actualizados), 'no_encontrados' => count($noEncontrados)],
            $request, "Importación CSV de descuentos, rol de pagos {$this->nombreMes((int)$request->mes)} {$request->anio}");

        return response()->json([
            'actualizados'   => count($actualizados),
            'no_encontrados' => $noEncontrados,
        ]);
    }

    // GET /api/nomina/rol-pago/{cabId}/resumenes
    public function resumenes(Request $request, $cabId)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $filas = DB::table('dbo.nom_rol_pago_det')
            ->where('cab_id', $cabId)
            ->selectRaw("
                COALESCE(programa, 'SIN PROGRAMA') as programa,
                COALESCE(actividad, 'SIN ACTIVIDAD') as actividad,
                COUNT(*) as empleados,
                COALESCE(SUM(valor_rmu), 0) as total_rmu,
                COALESCE(SUM(aporte_patronal + iece + secap), 0) as total_patronal,
                COALESCE(SUM(aporte_personal), 0) as total_personal,
                COALESCE(SUM(total_descuentos), 0) as total_descuentos,
                COALESCE(SUM(liquido), 0) as total_liquido
            ")
            ->groupBy('programa', 'actividad')
            ->orderBy('programa')
            ->orderBy('actividad')
            ->get();

        return response()->json($filas);
    }

    // GET /api/nomina/rol-pago/{cabId}/resumenes/pdf
    public function pdfResumenes($cabId, Request $request)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $cab = DB::table('dbo.nom_rol_pago_cab')->where('id', $cabId)->first();
        if (!$cab) {
            return response()->json(['message' => 'Período no encontrado.'], 404);
        }

        $filas = DB::table('dbo.nom_rol_pago_det')
            ->where('cab_id', $cabId)
            ->selectRaw("
                COALESCE(programa, 'SIN PROGRAMA') as programa,
                COALESCE(actividad, 'SIN ACTIVIDAD') as actividad,
                COUNT(*) as empleados,
                COALESCE(SUM(valor_rmu), 0) as total_rmu,
                COALESCE(SUM(aporte_patronal + iece + secap), 0) as total_patronal,
                COALESCE(SUM(aporte_personal), 0) as total_personal,
                COALESCE(SUM(total_descuentos), 0) as total_descuentos,
                COALESCE(SUM(liquido), 0) as total_liquido
            ")
            ->groupBy('programa', 'actividad')
            ->orderBy('programa')
            ->orderBy('actividad')
            ->get();

        $emp = $request->user();

        $pdf = Pdf::loadView('reportes.nom_rol_pago_resumenes', [
            'cab'         => $cab,
            'filas'       => $filas,
            'nombreMes'   => $this->nombreMes((int)$cab->mes),
            'anio'        => $cab->anio,
            'logo'        => $this->logoBase64(),
            'generadoPor' => $emp->nombre_emp . ' ' . $emp->apellido_emp,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("rol-pago-resumenes-{$cab->anio}-{$cab->mes}.pdf");
    }

    // GET /api/nomina/rol-pago/pdf
    public function pdf(Request $request)
    {
        $this->requireRole($request, self::ROLES_NOMINA);
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $cab = DB::table('dbo.nom_rol_pago_cab')
            ->where('anio', $request->anio)
            ->where('mes', $request->mes)
            ->first();

        if (!$cab) {
            return response()->json(['message' => 'Sin datos para el período seleccionado.'], 404);
        }

        $detalles = $this->detallesConEmpleado($cab->id);
        $emp      = $request->user();

        $pdf = Pdf::loadView('reportes.nom_rol_pago', [
            'cab'         => $cab,
            'detalles'    => $detalles,
            'nombreMes'   => $this->nombreMes((int)$request->mes),
            'anio'        => $request->anio,
            'logo'        => $this->logoBase64(),
            'generadoPor' => $emp->nombre_emp . ' ' . $emp->apellido_emp,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("rol-pago-{$request->anio}-{$request->mes}.pdf");
    }
}
