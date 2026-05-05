<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Nomina\DecimoTercero;
use App\Models\Nomina\DecimoCuarto;
use App\Models\Nomina\FondosReserva;
use App\Models\Nomina\SbuHistorico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class NominaController extends Controller
{
    private function esNominaOAdmin($id_emp): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TH NOMINA'])
            ->exists();
    }

    private function registrarAuditoria(Request $request, string $tabla, int $registroId, string $accion, $datosAnt, $datosNuevos, string $descripcion = null): void
    {
        $emp = $request->user();
        DB::table('dbo.nom_auditoria_log')->insert([
            'tabla'            => $tabla,
            'registro_id'      => $registroId,
            'accion'           => $accion,
            'datos_anteriores' => $datosAnt ? json_encode($datosAnt) : null,
            'datos_nuevos'     => $datosNuevos ? json_encode($datosNuevos) : null,
            'usuario_id'       => $emp->id_emp,
            'nombre_usuario'   => $emp->nombre_emp . ' ' . $emp->apellido_emp,
            'ip_origen'        => $request->ip(),
            'descripcion'      => $descripcion,
            'created_at'       => now(),
        ]);
    }

    private function calcularDiasEnMes(?string $fechaIngreso, int $anio, int $mes): int
    {
        if (!$fechaIngreso) return 30;

        $primerDia = Carbon::createFromDate($anio, $mes, 1)->startOfDay();
        $ingreso   = Carbon::parse($fechaIngreso)->startOfDay();

        if ($ingreso->lt($primerDia)) {
            return 30;
        }

        // Empleado ingresó dentro del mes; se usa base 30 días por mes
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
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return ucfirst($meses[$mes] ?? '');
    }

    // ── SBU Histórico ─────────────────────────────────────────────────────────

    // GET /api/nomina/sbu
    public function sbuIndex(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        return response()->json(SbuHistorico::orderByDesc('anio')->get());
    }

    // POST /api/nomina/sbu
    public function sbuStore(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate([
            'anio'  => 'required|integer|min:2020|max:2100',
            'valor' => 'required|numeric|min:1',
        ]);

        $sbu = SbuHistorico::updateOrCreate(
            ['anio' => $request->anio],
            ['valor' => $request->valor, 'usuario_registro' => $request->user()->id_emp]
        );
        return response()->json($sbu, 201);
    }

    // ── Auditoría ─────────────────────────────────────────────────────────────

    // GET /api/nomina/auditoria?tabla=&anio=&mes=
    public function auditoria(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $query = DB::table('dbo.nom_auditoria_log')->orderByDesc('created_at');

        if ($request->filled('tabla'))  $query->where('tabla', $request->tabla);
        if ($request->filled('anio'))   $query->where('descripcion', 'like', "%{$request->anio}%");
        if ($request->filled('usuario_id')) $query->where('usuario_id', $request->usuario_id);

        return response()->json($query->paginate(50));
    }

    // ── Décimo Tercero ────────────────────────────────────────────────────────

    // GET /api/nomina/decimo-tercero?anio=&mes=
    public function index13(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $registros = DecimoTercero::with('empleado.departamento')
            ->where('anio', $request->anio)
            ->where('mes', $request->mes)
            ->get();

        return response()->json([
            'registros'      => $registros,
            'estado_periodo' => $registros->first()?->estado ?? null,
            'total_empleados'=> $registros->count(),
            'total_valor'    => $registros->sum('valor'),
        ]);
    }

    // POST /api/nomina/decimo-tercero/calcular
    public function calcular13(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate([
            'anio' => 'required|integer|min:2020|max:2100',
            'mes'  => 'required|integer|min:1|max:12',
        ]);

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        if (DecimoTercero::where('anio', $anio)->where('mes', $mes)->where('estado', 'CERRADO')->exists()) {
            return response()->json(['message' => 'El período ya está CERRADO. No se puede recalcular.'], 422);
        }

        DecimoTercero::where('anio', $anio)->where('mes', $mes)->where('estado', 'BORRADOR')->delete();

        $empleados = Empleado::where('estado', 'ACTIVO')
            ->where('acumula_decimo_tercero', true)
            ->where('id_depto', '!=', 999)
            ->get();

        $emp = $request->user();
        $insertados = [];

        foreach ($empleados as $e) {
            $dias   = $this->calcularDiasEnMes($e->fecha_ingreso, $anio, $mes);
            $valor  = round((float)$e->sueldo / 12 / 30 * $dias, 2);

            $registro = DecimoTercero::create([
                'anio'         => $anio,
                'mes'          => $mes,
                'id_emp'       => $e->id_emp,
                'sueldo_base'  => $e->sueldo,
                'dias'         => $dias,
                'valor'        => $valor,
                'estado'       => 'BORRADOR',
                'creado_por'   => $emp->id_emp,
                'fecha_calculo'=> now(),
            ]);
            $insertados[] = $registro->id;
        }

        $this->registrarAuditoria($request, 'nom_decimo_tercero', 0, 'CALCULAR', null,
            ['anio' => $anio, 'mes' => $mes, 'total_empleados' => count($insertados)],
            "Cálculo período {$this->nombreMes($mes)} {$anio}");

        return $this->index13($request);
    }

    // POST /api/nomina/decimo-tercero/cerrar
    public function cerrar13(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        $count = DecimoTercero::where('anio', $anio)->where('mes', $mes)->where('estado', 'BORRADOR')->count();
        if ($count === 0) {
            return response()->json(['message' => 'No hay registros BORRADOR para cerrar en este período.'], 422);
        }

        $emp = $request->user();
        DecimoTercero::where('anio', $anio)->where('mes', $mes)->where('estado', 'BORRADOR')
            ->update(['estado' => 'CERRADO', 'cerrado_por' => $emp->id_emp, 'fecha_cierre' => now()]);

        $this->registrarAuditoria($request, 'nom_decimo_tercero', 0, 'CERRAR', null,
            ['anio' => $anio, 'mes' => $mes, 'total_empleados' => $count],
            "Cierre período {$this->nombreMes($mes)} {$anio}");

        return $this->index13($request);
    }

    // GET /api/nomina/decimo-tercero/pdf?anio=&mes=
    public function pdf13(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        $registros = DecimoTercero::with('empleado.departamento')
            ->where('anio', $anio)->where('mes', $mes)
            ->orderBy('id_emp')->get();

        if ($registros->isEmpty()) {
            return response()->json(['message' => 'No hay datos para este período.'], 404);
        }

        $pdf = Pdf::loadView('reportes.nom_decimo_tercero', [
            'registros' => $registros,
            'anio'      => $anio,
            'mes'       => $mes,
            'nombreMes' => $this->nombreMes($mes),
            'logo'      => $this->logoBase64(),
            'estado'    => $registros->first()->estado,
        ])->setPaper('letter', 'portrait');

        return $pdf->download("decimo_tercero_{$anio}_{$mes}.pdf");
    }

    // ── Décimo Cuarto ─────────────────────────────────────────────────────────

    // GET /api/nomina/decimo-cuarto?anio=&mes=
    public function index14(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        $registros = DecimoCuarto::with('empleado.departamento')
            ->where('anio', $anio)->where('mes', $mes)->get();

        $sbu = SbuHistorico::where('anio', $anio)->first();

        return response()->json([
            'registros'      => $registros,
            'sbu_anio'       => $sbu,
            'estado_periodo' => $registros->first()?->estado ?? null,
            'total_empleados'=> $registros->count(),
            'total_valor'    => $registros->sum('valor'),
        ]);
    }

    // POST /api/nomina/decimo-cuarto/calcular
    public function calcular14(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate([
            'anio' => 'required|integer|min:2020|max:2100',
            'mes'  => 'required|integer|min:1|max:12',
        ]);

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        $sbu = SbuHistorico::where('anio', $anio)->value('valor');
        if (!$sbu) {
            return response()->json([
                'message' => "No existe SBU registrado para el año {$anio}. Ingrese el SBU primero."
            ], 422);
        }

        if (DecimoCuarto::where('anio', $anio)->where('mes', $mes)->where('estado', 'CERRADO')->exists()) {
            return response()->json(['message' => 'El período ya está CERRADO. No se puede recalcular.'], 422);
        }

        DecimoCuarto::where('anio', $anio)->where('mes', $mes)->where('estado', 'BORRADOR')->delete();

        $empleados = Empleado::where('estado', 'ACTIVO')
            ->where('acumula_decimo_cuarto', true)
            ->where('id_depto', '!=', 999)
            ->get();

        $emp = $request->user();

        foreach ($empleados as $e) {
            $dias  = $this->calcularDiasEnMes($e->fecha_ingreso, $anio, $mes);
            $valor = round((float)$sbu / 12 / 30 * $dias, 2);

            DecimoCuarto::create([
                'anio'         => $anio,
                'mes'          => $mes,
                'id_emp'       => $e->id_emp,
                'sbu'          => $sbu,
                'dias'         => $dias,
                'valor'        => $valor,
                'estado'       => 'BORRADOR',
                'creado_por'   => $emp->id_emp,
                'fecha_calculo'=> now(),
            ]);
        }

        $this->registrarAuditoria($request, 'nom_decimo_cuarto', 0, 'CALCULAR', null,
            ['anio' => $anio, 'mes' => $mes, 'sbu' => $sbu],
            "Cálculo período {$this->nombreMes($mes)} {$anio}");

        return $this->index14($request);
    }

    // POST /api/nomina/decimo-cuarto/cerrar
    public function cerrar14(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        $count = DecimoCuarto::where('anio', $anio)->where('mes', $mes)->where('estado', 'BORRADOR')->count();
        if ($count === 0) {
            return response()->json(['message' => 'No hay registros BORRADOR para cerrar en este período.'], 422);
        }

        $emp = $request->user();
        DecimoCuarto::where('anio', $anio)->where('mes', $mes)->where('estado', 'BORRADOR')
            ->update(['estado' => 'CERRADO', 'cerrado_por' => $emp->id_emp, 'fecha_cierre' => now()]);

        $this->registrarAuditoria($request, 'nom_decimo_cuarto', 0, 'CERRAR', null,
            ['anio' => $anio, 'mes' => $mes, 'total_empleados' => $count],
            "Cierre período {$this->nombreMes($mes)} {$anio}");

        return $this->index14($request);
    }

    // GET /api/nomina/decimo-cuarto/pdf?anio=&mes=
    public function pdf14(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        $registros = DecimoCuarto::with('empleado.departamento')
            ->where('anio', $anio)->where('mes', $mes)
            ->orderBy('id_emp')->get();

        if ($registros->isEmpty()) {
            return response()->json(['message' => 'No hay datos para este período.'], 404);
        }

        $pdf = Pdf::loadView('reportes.nom_decimo_cuarto', [
            'registros' => $registros,
            'anio'      => $anio,
            'mes'       => $mes,
            'nombreMes' => $this->nombreMes($mes),
            'logo'      => $this->logoBase64(),
            'estado'    => $registros->first()->estado,
            'sbu'       => $registros->first()->sbu,
        ])->setPaper('letter', 'portrait');

        return $pdf->download("decimo_cuarto_{$anio}_{$mes}.pdf");
    }

    // ── Fondos de Reserva ────────────────────────────────────────────────────

    // GET /api/nomina/fondos-reserva?anio=&mes=
    public function indexFR(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $registros = FondosReserva::with('empleado.departamento')
            ->where('anio', $request->anio)
            ->where('mes', $request->mes)
            ->get();

        return response()->json([
            'registros'      => $registros,
            'estado_periodo' => $registros->first()?->estado ?? null,
            'total_empleados'=> $registros->count(),
            'total_valor'    => $registros->sum('valor'),
        ]);
    }

    // POST /api/nomina/fondos-reserva/calcular
    public function calcularFR(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate([
            'anio' => 'required|integer|min:2020|max:2100',
            'mes'  => 'required|integer|min:1|max:12',
        ]);

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        if (FondosReserva::where('anio', $anio)->where('mes', $mes)->where('estado', 'CERRADO')->exists()) {
            return response()->json(['message' => 'El período ya está CERRADO. No se puede recalcular.'], 422);
        }

        FondosReserva::where('anio', $anio)->where('mes', $mes)->where('estado', 'BORRADOR')->delete();

        // Fecha límite: el empleado debe tener ingreso <= primer día del mes - 12 meses
        $limiteFechaIngreso = Carbon::createFromDate($anio, $mes, 1)->subMonths(12)->toDateString();

        $empleados = Empleado::where('estado', 'ACTIVO')
            ->whereIn('acumula_fondos_reserva', [1, 2])
            ->where('id_depto', '!=', 999)
            ->whereNotNull('fecha_ingreso')
            ->where('fecha_ingreso', '<=', $limiteFechaIngreso)
            ->get();

        $emp = $request->user();

        foreach ($empleados as $e) {
            $dias  = $this->calcularDiasEnMes($e->fecha_ingreso, $anio, $mes);
            $valor = round((float)$e->sueldo * 8.33 / 100 / 30 * $dias, 2);
            $tipo  = (int)$e->acumula_fondos_reserva === 1 ? 'MENSUAL' : 'IESS';

            FondosReserva::create([
                'anio'         => $anio,
                'mes'          => $mes,
                'id_emp'       => $e->id_emp,
                'sueldo_base'  => $e->sueldo,
                'dias'         => $dias,
                'porcentaje'   => 8.33,
                'valor'        => $valor,
                'tipo'         => $tipo,
                'estado'       => 'BORRADOR',
                'creado_por'   => $emp->id_emp,
                'fecha_calculo'=> now(),
            ]);
        }

        $this->registrarAuditoria($request, 'nom_fondos_reserva', 0, 'CALCULAR', null,
            ['anio' => $anio, 'mes' => $mes, 'total_empleados' => $empleados->count()],
            "Cálculo período {$this->nombreMes($mes)} {$anio}");

        return $this->indexFR($request);
    }

    // POST /api/nomina/fondos-reserva/cerrar
    public function cerrarFR(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        $count = FondosReserva::where('anio', $anio)->where('mes', $mes)->where('estado', 'BORRADOR')->count();
        if ($count === 0) {
            return response()->json(['message' => 'No hay registros BORRADOR para cerrar en este período.'], 422);
        }

        $emp = $request->user();
        FondosReserva::where('anio', $anio)->where('mes', $mes)->where('estado', 'BORRADOR')
            ->update(['estado' => 'CERRADO', 'cerrado_por' => $emp->id_emp, 'fecha_cierre' => now()]);

        $this->registrarAuditoria($request, 'nom_fondos_reserva', 0, 'CERRAR', null,
            ['anio' => $anio, 'mes' => $mes, 'total_empleados' => $count],
            "Cierre período {$this->nombreMes($mes)} {$anio}");

        return $this->indexFR($request);
    }

    // GET /api/nomina/fondos-reserva/pdf?anio=&mes=
    public function pdfFR(Request $request)
    {
        if (!$this->esNominaOAdmin($request->user()->id_emp)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }
        $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12']);

        $anio = (int)$request->anio;
        $mes  = (int)$request->mes;

        $registros = FondosReserva::with('empleado.departamento')
            ->where('anio', $anio)->where('mes', $mes)
            ->orderBy('id_emp')->get();

        if ($registros->isEmpty()) {
            return response()->json(['message' => 'No hay datos para este período.'], 404);
        }

        $pdf = Pdf::loadView('reportes.nom_fondos_reserva', [
            'registros' => $registros,
            'anio'      => $anio,
            'mes'       => $mes,
            'nombreMes' => $this->nombreMes($mes),
            'logo'      => $this->logoBase64(),
            'estado'    => $registros->first()->estado,
        ])->setPaper('letter', 'portrait');

        return $pdf->download("fondos_reserva_{$anio}_{$mes}.pdf");
    }
}
