<?php
namespace App\Http\Controllers\Tecnologia;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\Tecnologia\Asignacion;
use App\Models\Tecnologia\Equipo;
use App\Models\Tecnologia\TipoEquipo;
use App\Services\AuditoriaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipoController extends Controller
{
    private const ROLES_TEC = ['ADMINISTRADOR', 'TECNOLOGIA'];

    private function vidaUtilVencidaRaw(): string
    {
        return "fecha_ingreso IS NOT NULL AND vida_util_anios IS NOT NULL
                AND (fecha_ingreso + (vida_util_anios || ' years')::interval) <= CURRENT_DATE";
    }

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $query = Equipo::with(['tipoEquipo', 'asignacionActiva.empleado'])->orderBy('codigo_bien');

        if ($request->filled('tipo_equipo_id')) {
            $query->where('tipo_equipo_id', $request->tipo_equipo_id);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->boolean('vida_util_vencida')) {
            $query->whereRaw($this->vidaUtilVencidaRaw());
        }
        if ($request->boolean('custodio_inactivo')) {
            $query->where('estado', 'ASIGNADO')
                  ->whereHas('asignacionActiva.empleado', fn ($q) => $q->where('estado', 'INACTIVO'));
        }
        if ($request->filled('busqueda')) {
            $b = $request->busqueda;
            $query->where(function ($q) use ($b) {
                $q->where('codigo_bien', 'ILIKE', "%$b%")
                  ->orWhere('serie', 'ILIKE', "%$b%")
                  ->orWhere('descripcion', 'ILIKE', "%$b%")
                  ->orWhere('marca', 'ILIKE', "%$b%")
                  ->orWhere('modelo', 'ILIKE', "%$b%");
            });
        }

        return response()->json($query->paginate(20));
    }

    public function resumen(Request $request)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $porEstado = Equipo::select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $vidaUtilVencida = Equipo::where('estado', '!=', 'DE_BAJA')
            ->whereRaw($this->vidaUtilVencidaRaw())
            ->count();

        $custodioInactivo = Equipo::where('estado', 'ASIGNADO')
            ->whereHas('asignacionActiva.empleado', fn ($q) => $q->where('estado', 'INACTIVO'))
            ->count();

        return response()->json([
            'total'             => (int) $porEstado->sum(),
            'disponible'        => (int) ($porEstado['DISPONIBLE'] ?? 0),
            'asignado'          => (int) ($porEstado['ASIGNADO'] ?? 0),
            'danado'            => (int) ($porEstado['DAÑADO'] ?? 0),
            'de_baja'           => (int) ($porEstado['DE_BAJA'] ?? 0),
            'vida_util_vencida' => (int) $vidaUtilVencida,
            'custodio_inactivo' => (int) $custodioInactivo,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $request->validate([
            'codigo_bien'     => 'required|string|max:50|unique:pgsql.dbo.ti_equipo,codigo_bien',
            'tipo_equipo_id'  => 'nullable|exists:pgsql.dbo.ti_tipo_equipo,id',
            'marca'           => 'nullable|string|max:150',
            'modelo'          => 'nullable|string|max:300',
            'descripcion'     => 'nullable|string|max:300',
            'serie'           => 'nullable|string|max:100',
            'condicion'       => 'nullable|in:BUENO,REGULAR,MALO',
            'fecha_ingreso'   => 'nullable|date',
            'vida_util_anios' => 'nullable|integer|min:0',
            'ubicacion'       => 'nullable|string|max:100',
            'observaciones'   => 'nullable|string',
        ]);

        $equipo = Equipo::create(array_merge(
            $request->only([
                'codigo_bien', 'tipo_equipo_id', 'marca', 'modelo', 'descripcion', 'serie',
                'condicion', 'fecha_ingreso', 'vida_util_anios', 'ubicacion', 'observaciones',
            ]),
            ['estado' => 'DISPONIBLE', 'created_by' => $request->user()->id_emp]
        ));

        return response()->json($equipo->load('tipoEquipo'), 201);
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $equipo = Equipo::findOrFail($id);

        $request->validate([
            'codigo_bien'     => 'required|string|max:50|unique:pgsql.dbo.ti_equipo,codigo_bien,' . $id,
            'tipo_equipo_id'  => 'nullable|exists:pgsql.dbo.ti_tipo_equipo,id',
            'marca'           => 'nullable|string|max:150',
            'modelo'          => 'nullable|string|max:300',
            'descripcion'     => 'nullable|string|max:300',
            'serie'           => 'nullable|string|max:100',
            'condicion'       => 'nullable|in:BUENO,REGULAR,MALO',
            'fecha_ingreso'   => 'nullable|date',
            'vida_util_anios' => 'nullable|integer|min:0',
            'ubicacion'       => 'nullable|string|max:100',
            'observaciones'   => 'nullable|string',
        ]);

        $equipo->update(array_merge(
            $request->only([
                'codigo_bien', 'tipo_equipo_id', 'marca', 'modelo', 'descripcion', 'serie',
                'condicion', 'fecha_ingreso', 'vida_util_anios', 'ubicacion', 'observaciones',
            ]),
            ['updated_by' => $request->user()->id_emp]
        ));

        return response()->json($equipo->load('tipoEquipo'));
    }

    public function asignar(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $equipo = Equipo::findOrFail($id);

        if ($equipo->estado !== 'DISPONIBLE') {
            return response()->json(['message' => 'El equipo no está disponible para asignar.'], 422);
        }

        $request->validate([
            'id_emp'           => 'required|exists:pgsql.dbo.ad_empleado,id_emp',
            'fecha_asignacion' => 'required|date',
        ]);

        Asignacion::create([
            'equipo_id'        => $equipo->id,
            'id_emp'           => $request->id_emp,
            'fecha_asignacion' => $request->fecha_asignacion,
            'usuario_asigna'   => $request->user()->id_emp,
        ]);

        $equipo->update(['estado' => 'ASIGNADO', 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.ti_equipo', $equipo->id, 'ASIGNAR',
            ['estado' => 'DISPONIBLE'],
            ['estado' => 'ASIGNADO', 'id_emp' => $request->id_emp],
            $request, "Asignación de equipo {$equipo->codigo_bien}");

        return response()->json($equipo->load(['tipoEquipo', 'asignacionActiva.empleado']));
    }

    public function devolver(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $equipo = Equipo::findOrFail($id);

        if ($equipo->estado !== 'ASIGNADO') {
            return response()->json(['message' => 'El equipo no está asignado.'], 422);
        }

        $request->validate([
            'motivo_devolucion' => 'required|in:REASIGNACION,SALIDA_EMPLEADO,DAÑO,OTRO',
            'fecha_devolucion'  => 'nullable|date',
            'observacion'       => 'nullable|string',
        ]);

        $asignacion = Asignacion::where('equipo_id', $equipo->id)->whereNull('fecha_devolucion')->firstOrFail();

        $asignacion->update([
            'fecha_devolucion'   => $request->fecha_devolucion ?? now()->toDateString(),
            'motivo_devolucion'  => $request->motivo_devolucion,
            'observacion'        => $request->observacion,
            'usuario_devolucion' => $request->user()->id_emp,
        ]);

        $nuevoEstado = $request->motivo_devolucion === 'DAÑO' ? 'DAÑADO' : 'DISPONIBLE';
        $equipo->update(['estado' => $nuevoEstado, 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.ti_equipo', $equipo->id, 'DEVOLVER',
            ['estado' => 'ASIGNADO'],
            ['estado' => $nuevoEstado, 'motivo_devolucion' => $request->motivo_devolucion],
            $request, "Devolución de equipo {$equipo->codigo_bien}");

        return response()->json($equipo->fresh(['tipoEquipo', 'asignacionActiva.empleado']));
    }

    public function historial(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $equipo = Equipo::findOrFail($id);

        return response()->json(
            Asignacion::with('empleado')
                ->where('equipo_id', $equipo->id)
                ->orderBy('fecha_asignacion', 'desc')
                ->get()
        );
    }

    public function marcarBaja(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $equipo = Equipo::findOrFail($id);

        if ($equipo->estado === 'ASIGNADO') {
            return response()->json(['message' => 'Debe devolver el equipo antes de darlo de baja.'], 422);
        }

        $request->validate([
            'motivo_baja'  => 'required|in:DAÑO_IRREPARABLE,OBSOLETO,ROBO_PERDIDA,FIN_VIDA_UTIL,OTRO',
            'detalle_baja' => 'required|string',
            'fecha_baja'   => 'nullable|date',
        ]);

        $estadoAnterior = $equipo->estado;
        $equipo->update([
            'estado'       => 'DE_BAJA',
            'motivo_baja'  => $request->motivo_baja,
            'detalle_baja' => $request->detalle_baja,
            'fecha_baja'   => $request->fecha_baja ?? now()->toDateString(),
            'updated_by'   => $request->user()->id_emp,
        ]);

        AuditoriaService::log('dbo.ti_equipo', $equipo->id, 'DAR_DE_BAJA',
            ['estado' => $estadoAnterior],
            ['estado' => 'DE_BAJA', 'motivo_baja' => $request->motivo_baja, 'detalle_baja' => $request->detalle_baja],
            $request, "Baja de equipo {$equipo->codigo_bien}: {$request->motivo_baja}");

        return response()->json($equipo);
    }

    public function marcarDisponible(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $equipo = Equipo::findOrFail($id);

        if ($equipo->estado === 'ASIGNADO') {
            return response()->json(['message' => 'El equipo ya está asignado.'], 422);
        }

        $estadoAnterior = $equipo->estado;
        $equipo->update(['estado' => 'DISPONIBLE', 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.ti_equipo', $equipo->id, 'MARCAR_DISPONIBLE',
            ['estado' => $estadoAnterior], ['estado' => 'DISPONIBLE'],
            $request, "Equipo {$equipo->codigo_bien} marcado disponible");

        return response()->json($equipo);
    }

    public function importarCsv(Request $request)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $request->validate(['archivo' => 'required|file|mimes:csv,txt|max:2048']);

        $path = $request->file('archivo')->getRealPath();

        // Excel en español suele exportar CSV separado por ';' en vez de ','
        $primeraLinea = file($path, FILE_IGNORE_NEW_LINES)[0] ?? '';
        $delimitador  = substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';

        $handle     = fopen($path, 'r');
        $encabezado = fgetcsv($handle, 0, $delimitador); // skip header

        $normalizarTipo = fn ($n) => strtoupper(trim(preg_replace('/\s+/', ' ', rtrim(trim($n), '.'))));

        $parseFecha = function (string $valor): ?string {
            if ($valor === '') return null;
            foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'] as $formato) {
                try {
                    $fecha = Carbon::createFromFormat($formato, $valor);
                    if ($fecha !== false) return $fecha->format('Y-m-d');
                } catch (\Exception $e) {
                    continue;
                }
            }
            return null;
        };

        $tiposPorNombre = [];
        foreach (TipoEquipo::all() as $t) {
            $tiposPorNombre[$normalizarTipo($t->nombre)] = $t->id;
        }

        $errores = [];
        $filas   = [];
        $fila    = 1;

        while (($row = fgetcsv($handle, 0, $delimitador)) !== false) {
            $fila++;
            if (count($row) < 8) {
                $errores[] = "Fila $fila: faltan columnas.";
                continue;
            }

            [$codigo_bien, $tipo_equipo, $marca, $modelo, $descripcion, $serie, $condicion, $fecha_ingreso] = array_map('trim', array_slice($row, 0, 8));
            $vida_util_anios = isset($row[8]) ? trim($row[8]) : null;

            if ($codigo_bien === '') {
                $errores[] = "Fila $fila: código de bien vacío.";
                continue;
            }

            $tipoEquipoId = null;
            if ($tipo_equipo !== '') {
                $clave = $normalizarTipo($tipo_equipo);
                if (!isset($tiposPorNombre[$clave])) {
                    $errores[] = "Fila $fila: tipo de equipo '$tipo_equipo' no existe en el catálogo.";
                    continue;
                }
                $tipoEquipoId = $tiposPorNombre[$clave];
            }

            if ($condicion !== '' && !in_array(strtoupper($condicion), ['BUENO', 'REGULAR', 'MALO'])) {
                $errores[] = "Fila $fila: condición '$condicion' inválida (BUENO/REGULAR/MALO).";
                continue;
            }

            $fechaIngresoParsed = $parseFecha($fecha_ingreso);
            if ($fecha_ingreso !== '' && $fechaIngresoParsed === null) {
                $errores[] = "Fila $fila: fecha de ingreso '$fecha_ingreso' no es válida (use DD/MM/AAAA).";
                continue;
            }

            $filas[] = [
                'codigo_bien'     => strtoupper($codigo_bien),
                'tipo_equipo_id'  => $tipoEquipoId,
                'marca'           => strtoupper($marca) ?: null,
                'modelo'          => strtoupper($modelo) ?: null,
                'descripcion'     => $descripcion ?: null,
                'serie'           => strtoupper($serie) ?: null,
                'condicion'       => $condicion !== '' ? strtoupper($condicion) : null,
                'fecha_ingreso'   => $fechaIngresoParsed,
                'vida_util_anios' => $vida_util_anios !== '' && $vida_util_anios !== null ? (int) $vida_util_anios : null,
                'estado'          => 'DISPONIBLE',
                'created_by'      => $request->user()->id_emp,
            ];
        }
        fclose($handle);

        if (!empty($errores)) {
            return response()->json(['message' => 'Errores en el CSV.', 'errores' => $errores], 422);
        }

        DB::transaction(function () use ($filas) {
            foreach ($filas as $data) {
                Equipo::create($data);
            }
        });

        $total = count($filas);
        return response()->json(['message' => "Se importaron $total equipo(s) correctamente."]);
    }
}
