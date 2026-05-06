<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class RolPagoController extends Controller
{
    private function esNominaOAdmin(string $id_emp): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TH NOMINA'])
            ->exists();
    }

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
            ->keyBy('modalidad');
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
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(valor_rmu),0) as bruto,
                         COALESCE(SUM(aporte_patronal),0) as patronal,
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
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $emp = $request->user();
        if (!$this->esNominaOAdmin($emp->id_emp)) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        $existeCerrado = DB::table('dbo.nom_rol_pago_cab')
            ->where('anio', $anio)->where('mes', $mes)->where('estado', 'CERRADO')
            ->exists();
        if ($existeCerrado) {
            return response()->json(['message' => 'El período ya está cerrado, no se puede recalcular.'], 422);
        }

        // Eliminar BORRADOR anterior
        $cabVieja = DB::table('dbo.nom_rol_pago_cab')
            ->where('anio', $anio)->where('mes', $mes)->first();
        if ($cabVieja) {
            DB::table('dbo.nom_rol_pago_det')->where('cab_id', $cabVieja->id)->delete();
            DB::table('dbo.nom_rol_pago_cab')->where('id', $cabVieja->id)->delete();
        }

        $tasas = $this->tasasVigentes();

        $empleados = DB::table('dbo.ad_empleado as e')
            ->join('dbo.ad_departamento as dep', 'e.id_depto', '=', 'dep.id_depto')
            ->where('e.estado', 'ACTIVO')
            ->where('e.id_depto', '<>', 999)
            ->select(
                'e.id_emp', 'e.nombre_emp', 'e.apellido_emp', 'e.identificacion',
                'e.sueldo', 'e.tipo_contrato', 'e.fecha_ingreso',
                'e.grupo_ocupacional as cargo', 'dep.nombre_depto'
            )
            ->get();

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

            $tasa         = $tasas->get($e->tipo_contrato);
            $patronalPct  = $tasa ? (float)$tasa->aporte_patronal  : 0;
            $personalPct  = $tasa ? (float)$tasa->aporte_individual : 0;

            $aporte_patronal  = round($valorRmu * $patronalPct  / 100, 2);
            $aporte_personal  = round($valorRmu * $personalPct  / 100, 2);
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
                'quirografario'       => 0,
                'hipotecario'         => 0,
                'impuesto_renta'      => 0,
                'supa'                => 0,
                'total_descuentos'    => $total_descuentos,
                'liquido'             => $liquido,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        $this->recalcularTotalesCab($cabId);

        $cab      = DB::table('dbo.nom_rol_pago_cab')->where('id', $cabId)->first();
        $detalles = $this->detallesConEmpleado($cabId);

        return response()->json(['cab' => $cab, 'detalles' => $detalles]);
    }

    // PUT /api/nomina/rol-pago/detalle/{id}
    public function updateDetalle(Request $request, $id)
    {
        $request->validate([
            'quirografario'  => 'nullable|numeric|min:0',
            'hipotecario'    => 'nullable|numeric|min:0',
            'impuesto_renta' => 'nullable|numeric|min:0',
            'supa'           => 'nullable|numeric|min:0',
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

        $total_descuentos = round($det->aporte_personal + $quirografario + $hipotecario + $impuesto_renta + $supa, 2);
        $liquido          = round($det->valor_rmu - $total_descuentos, 2);

        DB::table('dbo.nom_rol_pago_det')->where('id', $id)->update([
            'quirografario'   => $quirografario,
            'hipotecario'     => $hipotecario,
            'impuesto_renta'  => $impuesto_renta,
            'supa'            => $supa,
            'total_descuentos'=> $total_descuentos,
            'liquido'         => $liquido,
            'updated_at'      => now(),
        ]);

        $this->recalcularTotalesCab($det->cab_id);

        return response()->json(DB::table('dbo.nom_rol_pago_det')->where('id', $id)->first());
    }

    // POST /api/nomina/rol-pago/cerrar
    public function cerrar(Request $request)
    {
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $emp = $request->user();
        if (!$this->esNominaOAdmin($emp->id_emp)) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

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

        return response()->json(DB::table('dbo.nom_rol_pago_cab')->where('id', $cab->id)->first());
    }

    // GET /api/nomina/rol-pago/pdf
    public function pdf(Request $request)
    {
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
        ])->setPaper('letter', 'landscape');

        return $pdf->stream("rol-pago-{$request->anio}-{$request->mes}.pdf");
    }
}
