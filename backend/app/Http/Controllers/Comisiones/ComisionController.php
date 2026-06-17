<?php

namespace App\Http\Controllers\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\ComSolicitud;
use App\Models\ComSolicitudServidor;
use App\Models\ComSolicitudTransporte;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComisionController extends Controller
{
    // Roles del módulo
    private const ROL_MAXIMA_AUTORIDAD    = 'MAXIMA AUTORIDAD';
    private const ROL_DIR_ADM             = 'DIRECCION ADMINISTRATIVA';
    private const ROL_JURIDICA            = 'ASESORIA JURIDICA';
    private const ROL_CONTABILIDAD        = 'CONTABILIDAD';
    private const ROL_PRESUPUESTO         = 'PRESUPUESTO';
    private const ROL_DIR_FINANCIERO      = 'DIRECTOR FINANCIERO';
    private const ROL_TESORERIA           = 'TESORERIA';
    private const ROL_ADMIN               = 'ADMINISTRADOR';

    private function tieneRol(Request $request, string $rol): bool
    {
        $idEmp = $request->user()->id_emp;
        return DB::table('dbo.admin_usuario_rol')
            ->join('dbo.admin_rol', 'admin_usuario_rol.rol_id', '=', 'admin_rol.id')
            ->where('admin_usuario_rol.id_emp', $idEmp)
            ->where('admin_rol.nombre', $rol)
            ->exists();
    }

    public function miRol(Request $request): \Illuminate\Http\JsonResponse
    {
        $idEmp = $request->user()->id_emp;

        $roles = DB::table('dbo.admin_usuario_rol')
            ->join('dbo.admin_rol', 'admin_usuario_rol.rol_id', '=', 'admin_rol.id')
            ->where('admin_usuario_rol.id_emp', $idEmp)
            ->pluck('admin_rol.nombre')
            ->toArray();

        return response()->json([
            'es_admin'            => in_array(self::ROL_ADMIN, $roles),
            'es_maxima_autoridad' => in_array(self::ROL_MAXIMA_AUTORIDAD, $roles),
            'es_dir_adm'          => in_array(self::ROL_DIR_ADM, $roles),
            'es_juridica'         => in_array(self::ROL_JURIDICA, $roles),
            'es_contabilidad'     => in_array(self::ROL_CONTABILIDAD, $roles),
            'es_presupuesto'      => in_array(self::ROL_PRESUPUESTO, $roles),
            'es_dir_financiero'   => in_array(self::ROL_DIR_FINANCIERO, $roles),
            'es_tesoreria'        => in_array(self::ROL_TESORERIA, $roles),
            'es_supervisor'       => DB::table('dbo.supervisor_area')->where('id_emp', $idEmp)->exists(),
        ]);
    }

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user  = $request->user();
        $idEmp = $user->id_emp;

        $esAdmin     = $this->tieneRol($request, self::ROL_ADMIN);
        $esDirAdm    = $this->tieneRol($request, self::ROL_DIR_ADM);
        $esAutoridad = $this->tieneRol($request, self::ROL_MAXIMA_AUTORIDAD);
        $esFinanciero = $this->tieneRol($request, self::ROL_CONTABILIDAD)
                     || $this->tieneRol($request, self::ROL_PRESUPUESTO)
                     || $this->tieneRol($request, self::ROL_DIR_FINANCIERO)
                     || $this->tieneRol($request, self::ROL_TESORERIA);
        $esJuridica  = $this->tieneRol($request, self::ROL_JURIDICA);
        $esSupervisor = DB::table('dbo.supervisor_area')->where('id_emp', $idEmp)->exists();

        $query = DB::table('dbo.com_solicitud as s')
            ->join('dbo.ad_empleado as e', 's.id_emp', '=', 'e.id_emp')
            ->join('dbo.ad_departamento as d', 's.id_depto', '=', 'd.id_depto')
            ->select(
                's.id', 's.numero_solicitud', 's.tipo', 's.id_emp',
                DB::raw("TRIM(e.apellido_emp) || ' ' || TRIM(e.nombre_emp) AS nombre_empleado"),
                's.destino', 's.fecha_solicitud', 's.fecha_salida', 's.fecha_llegada',
                's.tiene_viaticos', 's.tiene_movilizaciones', 's.tiene_anticipo',
                's.estado', 's.created_at',
                'd.nombre_depto'
            )
            ->where('d.id_depto', '!=', 999);

        if ($esAdmin || $esAutoridad || $esDirAdm || $esFinanciero || $esJuridica) {
            // Ven todas las solicitudes
        } elseif ($esSupervisor) {
            // Ven las de su área + las propias
            $deptos = DB::table('dbo.supervisor_area')->where('id_emp', $idEmp)->pluck('id_depto');
            $query->where(function ($q) use ($idEmp, $deptos) {
                $q->where('s.id_emp', $idEmp)
                  ->orWhereIn('s.id_depto', $deptos);
            });
        } else {
            // Solo las propias
            $query->where('s.id_emp', $idEmp);
        }

        // Filtros opcionales
        if ($request->filled('estado')) {
            $query->where('s.estado', $request->estado);
        }
        if ($request->filled('tipo')) {
            $query->where('s.tipo', $request->tipo);
        }
        if ($request->filled('id_emp')) {
            $query->where('s.id_emp', $request->id_emp);
        }

        $solicitudes = $query->orderByDesc('s.created_at')->paginate(20);

        return response()->json($solicitudes);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'tipo'                   => 'required|in:INTERIOR,EXTERIOR',
            'fecha_solicitud'        => 'required|date',
            'destino'                => 'required|string|max:200',
            'fecha_salida'           => 'required|date',
            'hora_salida'            => 'required',
            'fecha_llegada'          => 'required|date|after_or_equal:fecha_salida',
            'hora_llegada'           => 'required',
            'descripcion_actividades'=> 'required|string',
            'servidores'             => 'array',
            'servidores.*.id_emp'    => 'required|string',
            'transportes'            => 'array',
        ]);

        $user  = $request->user();
        $idEmp = $user->id_emp;

        $depto = DB::table('dbo.ad_empleado')->where('id_emp', $idEmp)->value('id_depto');
        $unidad = DB::table('dbo.ad_departamento')->where('id_depto', $depto)->value('nombre_depto');

        DB::beginTransaction();
        try {
            $solicitud = ComSolicitud::create([
                'tipo'                   => $request->tipo,
                'id_emp'                 => $idEmp,
                'id_depto'               => $depto,
                'fecha_solicitud'        => $request->fecha_solicitud,
                'tiene_viaticos'         => $request->boolean('tiene_viaticos', true),
                'tiene_movilizaciones'   => $request->boolean('tiene_movilizaciones', false),
                'tiene_anticipo'         => $request->boolean('tiene_anticipo', false),
                'destino'                => $request->destino,
                'unidad_nombre'          => $unidad,
                'fecha_salida'           => $request->fecha_salida,
                'hora_salida'            => $request->hora_salida,
                'fecha_llegada'          => $request->fecha_llegada,
                'hora_llegada'           => $request->hora_llegada,
                'descripcion_actividades'=> $request->descripcion_actividades,
                'banco'                  => $request->banco,
                'tipo_cuenta'            => $request->tipo_cuenta,
                'numero_cuenta'          => $request->numero_cuenta,
                'estado'                 => 'BORRADOR',
                'created_by'             => $idEmp,
                'updated_by'             => $idEmp,
            ]);

            $this->syncServidores($solicitud->id, $request->servidores ?? [], $idEmp);
            $this->syncTransportes('solicitud', $solicitud->id, $request->transportes ?? []);

            AuditoriaService::log('dbo.com_solicitud', $solicitud->id, 'CREAR', null, ['tipo' => $solicitud->tipo, 'destino' => $solicitud->destino], $request, 'Solicitud de comisión creada');

            DB::commit();
            return response()->json($this->detalle($solicitud->id), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la solicitud: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->detalle($id));
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);

        if ($solicitud->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede editar una solicitud en BORRADOR'], 422);
        }

        $request->validate([
            'tipo'                   => 'required|in:INTERIOR,EXTERIOR',
            'fecha_solicitud'        => 'required|date',
            'destino'                => 'required|string|max:200',
            'fecha_salida'           => 'required|date',
            'hora_salida'            => 'required',
            'fecha_llegada'          => 'required|date|after_or_equal:fecha_salida',
            'hora_llegada'           => 'required',
            'descripcion_actividades'=> 'required|string',
        ]);

        $idEmp = $request->user()->id_emp;

        DB::beginTransaction();
        try {
            $solicitud->update([
                'tipo'                   => $request->tipo,
                'fecha_solicitud'        => $request->fecha_solicitud,
                'tiene_viaticos'         => $request->boolean('tiene_viaticos', true),
                'tiene_movilizaciones'   => $request->boolean('tiene_movilizaciones', false),
                'tiene_anticipo'         => $request->boolean('tiene_anticipo', false),
                'destino'                => $request->destino,
                'fecha_salida'           => $request->fecha_salida,
                'hora_salida'            => $request->hora_salida,
                'fecha_llegada'          => $request->fecha_llegada,
                'hora_llegada'           => $request->hora_llegada,
                'descripcion_actividades'=> $request->descripcion_actividades,
                'banco'                  => $request->banco,
                'tipo_cuenta'            => $request->tipo_cuenta,
                'numero_cuenta'          => $request->numero_cuenta,
                'updated_by'             => $idEmp,
            ]);

            $this->syncServidores($id, $request->servidores ?? [], $idEmp);
            $this->syncTransportes('solicitud', $id, $request->transportes ?? []);

            DB::commit();
            return response()->json($this->detalle($id));
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    // ─── Transiciones de estado ────────────────────────────────────────────────

    public function enviar(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);

        if ($solicitud->id_emp !== $request->user()->id_emp) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        if ($solicitud->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede enviar una solicitud en BORRADOR'], 422);
        }

        $anterior = $solicitud->estado;
        $solicitud->update(['estado' => 'PENDIENTE_DIR_ADM', 'updated_by' => $request->user()->id_emp]);

        AuditoriaService::log('dbo.com_solicitud', $id, 'ENVIAR', ['estado' => $anterior], ['estado' => 'PENDIENTE_DIR_ADM'], $request, 'Solicitud enviada a Dirección Administrativa');

        return response()->json(['estado' => $solicitud->estado]);
    }

    public function aprobarDirAdm(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        return $this->transicion($request, $id, self::ROL_DIR_ADM, 'PENDIENTE_DIR_ADM', 'PENDIENTE_JEFE', 'APROBAR_DIR_ADM', 'Aprobado por Dirección Administrativa');
    }

    public function negarDirAdm(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        return $this->negar($request, $id, self::ROL_DIR_ADM, 'PENDIENTE_DIR_ADM', 'NEGAR_DIR_ADM', 'Negado por Dirección Administrativa');
    }

    public function aprobarJefe(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);
        if ($solicitud->estado !== 'PENDIENTE_JEFE') {
            return response()->json(['message' => 'Estado incorrecto'], 422);
        }

        $idEmp = $request->user()->id_emp;
        $esSupervisorDepto = DB::table('dbo.supervisor_area')
            ->where('id_emp', $idEmp)
            ->where('id_depto', $solicitud->id_depto)
            ->exists();

        if (!$esSupervisorDepto && !$this->tieneRol($request, self::ROL_ADMIN)) {
            return response()->json(['message' => 'No es supervisor del departamento'], 403);
        }

        $anterior = $solicitud->estado;
        $solicitud->update(['estado' => 'PENDIENTE_AUTORIDAD', 'updated_by' => $idEmp]);
        AuditoriaService::log('dbo.com_solicitud', $id, 'APROBAR_JEFE', ['estado' => $anterior], ['estado' => 'PENDIENTE_AUTORIDAD'], $request, 'Aprobado por jefe inmediato');

        return response()->json(['estado' => $solicitud->estado]);
    }

    public function negarJefe(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);
        if ($solicitud->estado !== 'PENDIENTE_JEFE') {
            return response()->json(['message' => 'Estado incorrecto'], 422);
        }

        $idEmp = $request->user()->id_emp;
        $esSupervisorDepto = DB::table('dbo.supervisor_area')->where('id_emp', $idEmp)->where('id_depto', $solicitud->id_depto)->exists();
        if (!$esSupervisorDepto && !$this->tieneRol($request, self::ROL_ADMIN)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate(['observacion' => 'required|string']);
        $anterior = $solicitud->estado;
        $solicitud->update(['estado' => 'NEGADO', 'observacion' => $request->observacion, 'updated_by' => $idEmp]);
        AuditoriaService::log('dbo.com_solicitud', $id, 'NEGAR_JEFE', ['estado' => $anterior], ['estado' => 'NEGADO'], $request, 'Negado por jefe inmediato');

        return response()->json(['estado' => $solicitud->estado]);
    }

    public function aprobarAutoridad(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);
        if ($solicitud->estado !== 'PENDIENTE_AUTORIDAD') {
            return response()->json(['message' => 'Estado incorrecto'], 422);
        }

        if (!$this->tieneRol($request, self::ROL_MAXIMA_AUTORIDAD) && !$this->tieneRol($request, self::ROL_ADMIN)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Interior → AUTORIZADO | Exterior → PENDIENTE_JURIDICA
        $nuevoEstado = $solicitud->tipo === 'EXTERIOR' ? 'PENDIENTE_JURIDICA' : 'AUTORIZADO';

        // Si es AUTORIZADO, generar número de solicitud
        $data = ['estado' => $nuevoEstado, 'updated_by' => $request->user()->id_emp];
        if ($nuevoEstado === 'AUTORIZADO') {
            $data['numero_solicitud'] = $this->generarNumero($solicitud->id_depto);
        }

        $anterior = $solicitud->estado;
        $solicitud->update($data);
        AuditoriaService::log('dbo.com_solicitud', $id, 'APROBAR_AUTORIDAD', ['estado' => $anterior], $data, $request, 'Autorizado por Máxima Autoridad');

        return response()->json(['estado' => $solicitud->estado, 'numero_solicitud' => $solicitud->numero_solicitud]);
    }

    public function negarAutoridad(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        return $this->negar($request, $id, self::ROL_MAXIMA_AUTORIDAD, 'PENDIENTE_AUTORIDAD', 'NEGAR_AUTORIDAD', 'Negado por Máxima Autoridad');
    }

    public function emitirResolucion(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);
        if ($solicitud->estado !== 'PENDIENTE_JURIDICA') {
            return response()->json(['message' => 'Estado incorrecto'], 422);
        }
        if (!$this->tieneRol($request, self::ROL_JURIDICA) && !$this->tieneRol($request, self::ROL_ADMIN)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate(['resolucion_juridica' => 'required|string|max:100']);

        $solicitud->update([
            'estado'               => 'PENDIENTE_SISTEMA_EXT',
            'resolucion_juridica'  => $request->resolucion_juridica,
            'updated_by'           => $request->user()->id_emp,
        ]);
        AuditoriaService::log('dbo.com_solicitud', $id, 'EMITIR_RESOLUCION', ['estado' => 'PENDIENTE_JURIDICA'], ['estado' => 'PENDIENTE_SISTEMA_EXT', 'resolucion' => $request->resolucion_juridica], $request, 'Resolución jurídica emitida');

        return response()->json(['estado' => $solicitud->estado]);
    }

    public function registrarSistemaExt(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);
        if ($solicitud->estado !== 'PENDIENTE_SISTEMA_EXT') {
            return response()->json(['message' => 'Estado incorrecto'], 422);
        }

        $request->validate(['num_sistema_exterior' => 'required|string|max:100']);

        $numero = $this->generarNumero($solicitud->id_depto);
        $solicitud->update([
            'estado'               => 'AUTORIZADO',
            'num_sistema_exterior' => $request->num_sistema_exterior,
            'numero_solicitud'     => $numero,
            'updated_by'           => $request->user()->id_emp,
        ]);
        AuditoriaService::log('dbo.com_solicitud', $id, 'REGISTRAR_EXTERIOR', ['estado' => 'PENDIENTE_SISTEMA_EXT'], ['estado' => 'AUTORIZADO'], $request, 'Registrado en sistema exterior, comisión AUTORIZADA');

        return response()->json(['estado' => $solicitud->estado, 'numero_solicitud' => $solicitud->numero_solicitud]);
    }

    public function solicitarPago(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);
        if ($solicitud->estado !== 'INFORME_APROBADO') {
            return response()->json(['message' => 'El informe debe estar aprobado primero'], 422);
        }
        if ($solicitud->id_emp !== $request->user()->id_emp) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $solicitud->update(['estado' => 'EN_PAGO', 'updated_by' => $request->user()->id_emp]);
        AuditoriaService::log('dbo.com_solicitud', $id, 'SOLICITAR_PAGO', ['estado' => 'INFORME_APROBADO'], ['estado' => 'EN_PAGO'], $request, 'Empleado solicita pago de viáticos');

        return response()->json(['estado' => $solicitud->estado]);
    }

    // ─── PDF ──────────────────────────────────────────────────────────────────

    public function pdf(int $id)
    {
        $solicitud = ComSolicitud::with(['empleado', 'servidores.empleado', 'transportes'])->findOrFail($id);
        $config    = DB::table('dbo.d2_configuracion')->get()->keyBy(fn($r) => strtolower($r->concepto));

        $nombreInst    = 'CONSEJO DE COMUNICACIÓN';
        $logo          = file_exists(public_path('logo.png')) ? base64_encode(file_get_contents(public_path('logo.png'))) : null;
        $generadoPor   = trim($solicitud->empleado->apellido_emp ?? '') . ' ' . trim($solicitud->empleado->nombre_emp ?? '');
        $firmanteAutoridad = $config['firmante_autoridad_nombre']->valor ?? '';
        $firmanteAutoridadCargo = $config['firmante_autoridad_cargo']->valor ?? '';

        $template = $solicitud->tipo === 'EXTERIOR'
            ? 'reportes.com_solicitud_exterior'
            : 'reportes.com_solicitud_interior';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($template, compact(
            'solicitud', 'logo', 'nombreInst', 'generadoPor',
            'firmanteAutoridad', 'firmanteAutoridadCargo'
        ))->setPaper('a4', 'portrait');

        $numero = $solicitud->numero_solicitud ?? ('COM-' . $id);
        return $pdf->download("solicitud-{$numero}.pdf");
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generarNumero(int $idDepto): string
    {
        $centroCosto = DB::table('dbo.ad_departamento')
            ->where('id_depto', $idDepto)
            ->value('centro_de_costo') ?? $idDepto;

        $anio = now()->year;
        $max  = DB::table('dbo.com_solicitud')
            ->whereNotNull('numero_solicitud')
            ->whereYear('created_at', $anio)
            ->selectRaw("MAX(CAST(SPLIT_PART(numero_solicitud, '-', 4) AS INTEGER)) AS max_num")
            ->value('max_num') ?? 0;
        $seq  = str_pad($max + 1, 3, '0', STR_PAD_LEFT);
        return "CS-{$centroCosto}-{$anio}-{$seq}";
    }

    public function buscarServidor(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $like = '%' . strtoupper($q) . '%';

        // Buscar en empleados activos
        $empleados = DB::table('dbo.ad_empleado as e')
            ->join('dbo.ad_departamento as d', 'e.id_depto', '=', 'd.id_depto')
            ->where('e.estado', 'ACTIVO')
            ->where(function ($query) use ($like, $q) {
                $query->whereRaw("e.identificacion ILIKE ?", [$like])
                      ->orWhereRaw("UPPER(TRIM(e.apellido_emp) || ' ' || TRIM(e.nombre_emp)) ILIKE ?", [$like]);
            })
            ->select(
                'e.identificacion as cedula',
                DB::raw("TRIM(e.apellido_emp) || ' ' || TRIM(e.nombre_emp) AS nombres"),
                'e.cargo_empleado as cargo',
                'd.nombre_depto as unidad'
            )
            ->limit(6)
            ->get()
            ->map(fn($r) => [...(array)$r, 'tipo' => 'EMPLEADO']);

        // Buscar en funcionarios externos activos
        $externos = DB::table('dbo.com_funcionario_externo')
            ->where('activo', true)
            ->where(function ($query) use ($like) {
                $query->whereRaw("cedula ILIKE ?", [$like])
                      ->orWhereRaw("UPPER(nombres) ILIKE ?", [$like]);
            })
            ->select('cedula', 'nombres', 'cargo', DB::raw("'Funcionario Externo' as unidad"))
            ->limit(4)
            ->get()
            ->map(fn($r) => [...(array)$r, 'tipo' => 'EXTERNO']);

        return response()->json($empleados->concat($externos)->values());
    }

    private function detalle(int $id): array
    {
        $s = ComSolicitud::with(['empleado', 'servidores.empleado', 'transportes', 'informe', 'anticipo', 'fichaLiquidacion'])->findOrFail($id);

        return [
            'id'                     => $s->id,
            'numero_solicitud'       => $s->numero_solicitud,
            'tipo'                   => $s->tipo,
            'id_emp'                 => $s->id_emp,
            'nombre_empleado'        => trim($s->empleado->apellido_emp ?? '') . ' ' . trim($s->empleado->nombre_emp ?? ''),
            'destino'                => $s->destino,
            'unidad_nombre'          => $s->unidad_nombre,
            'fecha_solicitud'        => $s->fecha_solicitud?->format('Y-m-d'),
            'fecha_salida'           => $s->fecha_salida?->format('Y-m-d'),
            'hora_salida'            => $s->hora_salida,
            'fecha_llegada'          => $s->fecha_llegada?->format('Y-m-d'),
            'hora_llegada'           => $s->hora_llegada,
            'tiene_viaticos'         => $s->tiene_viaticos,
            'tiene_movilizaciones'   => $s->tiene_movilizaciones,
            'tiene_anticipo'         => $s->tiene_anticipo,
            'descripcion_actividades'=> $s->descripcion_actividades,
            'banco'                  => $s->banco,
            'tipo_cuenta'            => $s->tipo_cuenta,
            'numero_cuenta'          => $s->numero_cuenta,
            'estado'                 => $s->estado,
            'observacion'            => $s->observacion,
            'num_sistema_exterior'   => $s->num_sistema_exterior,
            'resolucion_juridica'    => $s->resolucion_juridica,
            'servidores'             => $s->servidores->map(fn($srv) => [
                'id'     => $srv->id,
                'id_emp' => $srv->id_emp,
                'nombre' => trim($srv->empleado->apellido_emp ?? '') . ' ' . trim($srv->empleado->nombre_emp ?? ''),
                'unidad' => $srv->unidad,
                'puesto' => $srv->puesto,
            ]),
            'transportes'            => $s->transportes,
            'informe'                => $s->informe,
            'anticipo'               => $s->anticipo,
            'ficha_liquidacion'      => $s->fichaLiquidacion,
            'created_at'             => $s->created_at,
        ];
    }

    private function syncServidores(int $solicitudId, array $servidores, string $idEmpUser): void
    {
        DB::table('dbo.com_solicitud_servidor')->where('solicitud_id', $solicitudId)->delete();
        foreach ($servidores as $i => $srv) {
            if (empty($srv['id_emp'])) continue;

            // Buscar primero en empleados, luego en externos
            $emp     = DB::table('dbo.ad_empleado')->where('id_emp', $srv['id_emp'])->first();
            $externo = !$emp ? DB::table('dbo.com_funcionario_externo')->where('cedula', $srv['id_emp'])->first() : null;

            $unidad = $srv['unidad']
                ?? ($emp ? DB::table('dbo.ad_departamento')->where('id_depto', $emp->id_depto)->value('nombre_depto') : null)
                ?? ($externo ? 'Funcionario Externo' : null);

            $puesto = $srv['puesto']
                ?? ($emp->cargo_empleado ?? null)
                ?? ($externo->cargo ?? null);

            ComSolicitudServidor::create([
                'solicitud_id'  => $solicitudId,
                'id_emp'        => $srv['id_emp'],
                'unidad'        => $unidad,
                'puesto'        => $puesto,
                'orden'         => $i + 1,
                'banco'         => $srv['banco'] ?? null,
                'tipo_cuenta'   => $srv['tipo_cuenta'] ?? null,
                'numero_cuenta' => $srv['numero_cuenta'] ?? null,
            ]);
        }
    }

    private function syncTransportes(string $tipo, int $parentId, array $transportes): void
    {
        if ($tipo === 'solicitud') {
            DB::table('dbo.com_solicitud_transporte')->where('solicitud_id', $parentId)->delete();
            foreach ($transportes as $i => $trn) {
                ComSolicitudTransporte::create([
                    'solicitud_id'  => $parentId,
                    'tipo'          => $trn['tipo'] ?? null,
                    'nombre'        => $trn['nombre'] ?? null,
                    'ruta'          => $trn['ruta'] ?? null,
                    'salida_fecha'  => $trn['salida_fecha'] ?? null,
                    'salida_hora'   => $trn['salida_hora'] ?? null,
                    'llegada_fecha' => $trn['llegada_fecha'] ?? null,
                    'llegada_hora'  => $trn['llegada_hora'] ?? null,
                    'orden'         => $i + 1,
                ]);
            }
        }
    }

    private function transicion(Request $request, int $id, string $rol, string $estadoRequerido, string $nuevoEstado, string $accion, string $descripcion): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);
        if ($solicitud->estado !== $estadoRequerido) {
            return response()->json(['message' => 'Estado incorrecto para esta acción'], 422);
        }
        if (!$this->tieneRol($request, $rol) && !$this->tieneRol($request, self::ROL_ADMIN)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        $anterior = $solicitud->estado;
        $solicitud->update(['estado' => $nuevoEstado, 'updated_by' => $request->user()->id_emp]);
        AuditoriaService::log('dbo.com_solicitud', $id, $accion, ['estado' => $anterior], ['estado' => $nuevoEstado], $request, $descripcion);
        return response()->json(['estado' => $solicitud->estado]);
    }

    private function negar(Request $request, int $id, string $rol, string $estadoRequerido, string $accion, string $descripcion): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);
        if ($solicitud->estado !== $estadoRequerido) {
            return response()->json(['message' => 'Estado incorrecto'], 422);
        }
        if (!$this->tieneRol($request, $rol) && !$this->tieneRol($request, self::ROL_ADMIN)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        $request->validate(['observacion' => 'required|string']);
        $anterior = $solicitud->estado;
        $solicitud->update(['estado' => 'NEGADO', 'observacion' => $request->observacion, 'updated_by' => $request->user()->id_emp]);
        AuditoriaService::log('dbo.com_solicitud', $id, $accion, ['estado' => $anterior], ['estado' => 'NEGADO'], $request, $descripcion);
        return response()->json(['estado' => $solicitud->estado]);
    }
}
