<?php

namespace App\Http\Controllers\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\ComSolicitud;
use App\Models\ComSolicitudServidor;
use App\Models\ComSolicitudTransporte;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ComisionController extends Controller
{
    private string $alfrescoBase = 'http://192.168.26.38:8080/alfresco/api/-default-/public/alfresco/versions/1';
    private string $alfrescoUser = 'admin';
    private string $alfrescoPass = 'admin';
    private string $alfrescoSite = 'talentohumano';

    private const ROL_ADMIN = 'ADMINISTRADOR';

    private function tieneRol(Request $request, string $rol): bool
    {
        $idEmp = $request->user()->id_emp;
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $idEmp)
            ->where('r.descripcion', $rol)
            ->exists();
    }

    private function getDocLibNodeId(): string
    {
        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/sites/{$this->alfrescoSite}/containers/documentLibrary");
        if (!$resp->successful()) abort(502, 'No se pudo conectar con Alfresco');
        return $resp->json('entry.id');
    }

    public function miRol(Request $request): \Illuminate\Http\JsonResponse
    {
        $idEmp = $request->user()->id_emp;

        $roles = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $idEmp)
            ->pluck('r.descripcion')
            ->toArray();

        return response()->json([
            'es_admin'            => in_array('ADMINISTRADOR', $roles),
            'es_maxima_autoridad' => in_array('MAXIMA AUTORIDAD', $roles),
            'es_dir_adm'          => in_array('DIRECCION ADMINISTRATIVA', $roles),
            'es_juridica'         => in_array('ASESORIA JURIDICA', $roles),
            'es_contabilidad'     => in_array('CONTABILIDAD', $roles),
            'es_presupuesto'      => in_array('PRESUPUESTO', $roles),
            'es_dir_financiero'   => in_array('DIRECTOR FINANCIERO', $roles),
            'es_tesoreria'        => in_array('TESORERIA', $roles),
            'es_supervisor'       => DB::table('dbo.supervisor_area')->where('id_emp', $idEmp)->exists(),
        ]);
    }

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user  = $request->user();
        $idEmp = $user->id_emp;

        $esAdmin     = $this->tieneRol($request, 'ADMINISTRADOR');
        $esFinanciero = $this->tieneRol($request, 'CONTABILIDAD')
                     || $this->tieneRol($request, 'PRESUPUESTO')
                     || $this->tieneRol($request, 'DIRECTOR FINANCIERO')
                     || $this->tieneRol($request, 'TESORERIA');
        $esSupervisor = DB::table('dbo.supervisor_area')->where('id_emp', $idEmp)->exists();

        $query = DB::table('dbo.com_solicitud as s')
            ->join('dbo.ad_empleado as e', 's.id_emp', '=', 'e.id_emp')
            ->leftJoin('dbo.ad_departamento as d', 's.id_depto', '=', 'd.id_depto')
            ->select(
                's.id', 's.numero_solicitud', 's.tipo', 's.id_emp',
                DB::raw("TRIM(e.apellido_emp) || ' ' || TRIM(e.nombre_emp) AS nombre_empleado"),
                's.destino', 's.fecha_solicitud', 's.fecha_salida', 's.fecha_llegada',
                's.tiene_viaticos', 's.tiene_movilizaciones', 's.tiene_anticipo',
                's.estado', 's.created_at',
                DB::raw("COALESCE(d.nombre_depto, '') AS nombre_depto"),
                DB::raw("(SELECT COALESCE(COUNT(*), 0) FROM dbo.com_solicitud_documento sd WHERE sd.solicitud_id = s.id AND sd.tipo_doc IN ('AUTORIZACION','PASAJES','CERTIFICACION')) AS docs_count")
            )
            ->where(function ($q) {
                $q->whereNull('d.id_depto')->orWhere('d.id_depto', '!=', 999);
            });

        if ($esAdmin || $esFinanciero) {
            // Ven todas las solicitudes
        } elseif ($esSupervisor) {
            $deptos = DB::table('dbo.supervisor_area')->where('id_emp', $idEmp)->pluck('id_depto');
            $query->where(function ($q) use ($idEmp, $deptos) {
                $q->where('s.id_emp', $idEmp)
                  ->orWhereIn('s.id_depto', $deptos);
            });
        } else {
            $query->where('s.id_emp', $idEmp);
        }

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
            'destino'                => 'required|string|max:200',
            'fecha_salida'           => 'required|date',
            'hora_salida'            => 'required',
            'fecha_llegada'          => 'required|date|after_or_equal:fecha_salida',
            'hora_llegada'           => 'required',
            'descripcion_actividades'=> 'required|string',
            'transportes'            => 'array',
        ]);

        $emp   = $request->user();
        $idEmp = $emp->id_emp;
        $depto = DB::table('dbo.ad_departamento')->where('id_depto', $emp->id_depto)->first();

        DB::beginTransaction();
        try {
            $solicitud = ComSolicitud::create([
                'tipo'                   => $request->tipo,
                'id_emp'                 => $idEmp,
                'id_depto'               => $emp->id_depto,
                'fecha_solicitud'        => now()->toDateString(),
                'tiene_viaticos'         => $request->boolean('tiene_viaticos', true),
                'tiene_movilizaciones'   => $request->boolean('tiene_movilizaciones', false),
                'tiene_anticipo'         => $request->boolean('tiene_anticipo', false),
                'destino'                => $request->destino,
                'unidad_nombre'          => $depto->nombre_depto ?? '',
                'fecha_salida'           => $request->fecha_salida,
                'hora_salida'            => $request->hora_salida,
                'fecha_llegada'          => $request->fecha_llegada,
                'hora_llegada'           => $request->hora_llegada,
                'descripcion_actividades'=> $request->descripcion_actividades,
                'banco'                  => $emp->banco,
                'tipo_cuenta'            => $emp->tipo_cuenta,
                'numero_cuenta'          => $emp->numero_cuenta,
                'estado'                 => 'BORRADOR',
                'created_by'             => $idEmp,
                'updated_by'             => $idEmp,
            ]);

            // Auto-insertar el servidor (empleado logueado)
            DB::table('dbo.com_solicitud_servidor')->insert([
                'solicitud_id'  => $solicitud->id,
                'id_emp'        => $idEmp,
                'unidad'        => $depto->nombre_depto ?? '',
                'puesto'        => $emp->cargo_empleado ?? '',
                'banco'         => $emp->banco,
                'tipo_cuenta'   => $emp->tipo_cuenta,
                'numero_cuenta' => $emp->numero_cuenta,
                'orden'         => 1,
            ]);

            $this->syncTransportes('solicitud', $solicitud->id, $request->transportes ?? []);

            AuditoriaService::log('dbo.com_solicitud', $solicitud->id, 'CREAR', null, ['tipo' => $solicitud->tipo, 'destino' => $solicitud->destino], $request, 'Solicitud de comisión creada');

            DB::commit();
            return response()->json([
                'id'     => $solicitud->id,
                'estado' => 'BORRADOR',
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la solicitud: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        return $this->detalle($id);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $solicitud = ComSolicitud::findOrFail($id);

        if ($solicitud->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede editar una solicitud en BORRADOR'], 422);
        }

        $request->validate([
            'tipo'                   => 'required|in:INTERIOR,EXTERIOR',
            'destino'                => 'required|string|max:200',
            'fecha_salida'           => 'required|date',
            'hora_salida'            => 'required',
            'fecha_llegada'          => 'required|date|after_or_equal:fecha_salida',
            'hora_llegada'           => 'required',
            'descripcion_actividades'=> 'required|string',
        ]);

        $emp   = $request->user();
        $idEmp = $emp->id_emp;
        $depto = DB::table('dbo.ad_departamento')->where('id_depto', $emp->id_depto)->first();

        DB::beginTransaction();
        try {
            $solicitud->update([
                'tipo'                   => $request->tipo,
                'tiene_viaticos'         => $request->boolean('tiene_viaticos', true),
                'tiene_movilizaciones'   => $request->boolean('tiene_movilizaciones', false),
                'tiene_anticipo'         => $request->boolean('tiene_anticipo', false),
                'destino'                => $request->destino,
                'fecha_salida'           => $request->fecha_salida,
                'hora_salida'            => $request->hora_salida,
                'fecha_llegada'          => $request->fecha_llegada,
                'hora_llegada'           => $request->hora_llegada,
                'descripcion_actividades'=> $request->descripcion_actividades,
                'updated_by'             => $idEmp,
            ]);

            // Actualizar servidor (datos bancarios pueden haber cambiado)
            DB::table('dbo.com_solicitud_servidor')->updateOrInsert(
                ['solicitud_id' => $id, 'id_emp' => $idEmp],
                [
                    'unidad'        => $depto->nombre_depto ?? '',
                    'puesto'        => $emp->cargo_empleado ?? '',
                    'banco'         => $emp->banco,
                    'tipo_cuenta'   => $emp->tipo_cuenta,
                    'numero_cuenta' => $emp->numero_cuenta,
                    'orden'         => 1,
                ]
            );

            $this->syncTransportes('solicitud', $id, $request->transportes ?? []);

            DB::commit();
            return $this->detalle($id);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    // ─── Flujo documentos y aprobación ───────────────────────────────────────

    public function uploadDocumento(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'tipo_doc' => 'required|in:AUTORIZACION,PASAJES,CERTIFICACION',
            'archivo'  => 'required|file|mimes:pdf|max:10240',
        ]);

        $solicitud = DB::table('dbo.com_solicitud')
            ->where('id', $id)
            ->where('id_emp', $request->user()->id_emp)
            ->first();

        if (!$solicitud) abort(403, 'No autorizado');
        if ($solicitud->estado !== 'BORRADOR') abort(422, 'Solo se pueden subir documentos en BORRADOR.');

        $emp      = $request->user();
        $anio     = now()->year;
        $apellido = strtoupper(trim($emp->apellido_emp ?? ''));
        $cedula   = $emp->identificacion ?? $emp->id_emp;
        $carpeta  = "comisiones/{$anio}/{$cedula}_{$apellido}";
        $nombre   = strtoupper($request->tipo_doc) . '_' . $id . '_' . now()->format('YmdHis') . '.pdf';

        $docLibId = $this->getDocLibNodeId();
        $upload = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach('filedata', file_get_contents($request->file('archivo')->getRealPath()), $nombre)
            ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
                'name'         => $nombre,
                'nodeType'     => 'cm:content',
                'relativePath' => $carpeta,
                'autoRename'   => true,
            ]);

        if (!$upload->successful()) abort(502, 'No se pudo subir el documento a Alfresco.');

        // Un solo slot por tipo — reemplazar si ya existía
        DB::table('dbo.com_solicitud_documento')
            ->where('solicitud_id', $id)
            ->where('tipo_doc', $request->tipo_doc)
            ->delete();

        DB::table('dbo.com_solicitud_documento')->insert([
            'solicitud_id'   => $id,
            'tipo_doc'       => $request->tipo_doc,
            'alfresco_id'    => $upload->json('entry.id'),
            'nombre_archivo' => $nombre,
            'created_by'     => $emp->id_emp,
            'created_at'     => now(),
        ]);

        return response()->json(['message' => 'Documento subido correctamente.']);
    }

    public function deleteDocumento(Request $request, int $id, int $docId): \Illuminate\Http\JsonResponse
    {
        $solicitud = DB::table('dbo.com_solicitud')
            ->where('id', $id)
            ->where('id_emp', $request->user()->id_emp)
            ->first();

        if (!$solicitud) abort(403, 'No autorizado');
        if ($solicitud->estado !== 'BORRADOR') abort(422, 'No se puede eliminar documentos fuera de BORRADOR.');

        DB::table('dbo.com_solicitud_documento')
            ->where('id', $docId)
            ->where('solicitud_id', $id)
            ->delete();

        return response()->json(['message' => 'Documento eliminado.']);
    }

    public function descargarDocumento(Request $request, int $id, int $docId)
    {
        $doc = DB::table('dbo.com_solicitud_documento')
            ->where('id', $docId)
            ->where('solicitud_id', $id)
            ->first();

        if (!$doc) abort(404, 'Documento no encontrado.');

        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/nodes/{$doc->alfresco_id}/content");

        if (!$resp->successful()) abort(502, 'No se pudo descargar el documento.');

        return response($resp->body(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($doc->nombre_archivo ?? 'documento.pdf') . '"',
        ]);
    }

    public function procesar(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $solicitud = DB::table('dbo.com_solicitud')
            ->where('id', $id)
            ->where('id_emp', $request->user()->id_emp)
            ->first();

        if (!$solicitud) abort(403, 'No autorizado.');
        if ($solicitud->estado !== 'BORRADOR') abort(422, 'Solo se puede procesar una solicitud en BORRADOR.');

        $docsCount = DB::table('dbo.com_solicitud_documento')
            ->where('solicitud_id', $id)
            ->whereIn('tipo_doc', ['AUTORIZACION', 'PASAJES', 'CERTIFICACION'])
            ->count();

        if ($docsCount < 3) abort(422, 'Debe subir los 3 documentos requeridos antes de procesar.');

        $numero = $this->generarNumero($solicitud->id_depto);

        DB::table('dbo.com_solicitud')->where('id', $id)->update([
            'estado'           => 'PROCESADO',
            'numero_solicitud' => $numero,
            'updated_by'       => $request->user()->id_emp,
            'updated_at'       => now(),
        ]);

        AuditoriaService::log('dbo.com_solicitud', $id, 'PROCESAR', ['estado' => 'BORRADOR'], ['estado' => 'PROCESADO', 'numero' => $numero], $request, 'Solicitud procesada');

        return response()->json(['message' => 'Solicitud procesada correctamente.', 'numero' => $numero]);
    }

    public function subirFirmado(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $request->validate(['archivo' => 'required|file|mimes:pdf|max:20480']);

        $solicitud = DB::table('dbo.com_solicitud')
            ->where('id', $id)
            ->where('id_emp', $request->user()->id_emp)
            ->first();

        if (!$solicitud) abort(403, 'No autorizado');
        if ($solicitud->estado !== 'PROCESADO') abort(422, 'La solicitud debe estar en PROCESADO.');

        $emp      = $request->user();
        $anio     = now()->year;
        $apellido = strtoupper(trim($emp->apellido_emp ?? ''));
        $cedula   = $emp->identificacion ?? $emp->id_emp;
        $carpeta  = "comisiones/{$anio}/{$cedula}_{$apellido}";
        $nombre   = 'SOLICITUD_FIRMADA_' . $id . '_' . now()->format('YmdHis') . '.pdf';

        $docLibId = $this->getDocLibNodeId();
        $upload = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach('filedata', file_get_contents($request->file('archivo')->getRealPath()), $nombre)
            ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
                'name'         => $nombre,
                'nodeType'     => 'cm:content',
                'relativePath' => $carpeta,
                'autoRename'   => true,
            ]);

        if (!$upload->successful()) abort(502, 'No se pudo subir el documento a Alfresco.');

        DB::table('dbo.com_solicitud_documento')->insert([
            'solicitud_id'   => $id,
            'tipo_doc'       => 'FIRMADO',
            'alfresco_id'    => $upload->json('entry.id'),
            'nombre_archivo' => $nombre,
            'created_by'     => $emp->id_emp,
            'created_at'     => now(),
        ]);

        $numero = $this->generarNumero($solicitud->id_depto);

        DB::table('dbo.com_solicitud')->where('id', $id)->update([
            'estado'           => 'APROBADO',
            'numero_solicitud' => $numero,
            'updated_by'       => $emp->id_emp,
            'updated_at'       => now(),
        ]);

        AuditoriaService::log('dbo.com_solicitud', $id, 'APROBAR', ['estado' => 'BORRADOR'], ['estado' => 'APROBADO', 'numero' => $numero], $request, 'Solicitud aprobada con PDF firmado');

        return response()->json(['message' => 'Solicitud aprobada.', 'numero' => $numero]);
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

    // ─── Provincias ───────────────────────────────────────────────────────────

    public function provincias(): \Illuminate\Http\JsonResponse
    {
        $provincias = DB::table('dbo.com_provincia')->orderBy('nombre')->get();

        $ciudades = DB::table('dbo.com_ciudad')->orderBy('nombre')->get()->groupBy('provincia_id');

        $resultado = $provincias->map(fn($p) => [
            'id'       => $p->id,
            'nombre'   => $p->nombre,
            'ciudades' => ($ciudades[$p->id] ?? collect())->map(fn($c) => [
                'id'     => $c->id,
                'nombre' => $c->nombre,
            ])->values(),
        ]);

        return response()->json($resultado);
    }

    // ─── Búsqueda de servidores ───────────────────────────────────────────────

    public function buscarServidor(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $like = '%' . strtoupper($q) . '%';

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
                'd.nombre_depto as unidad',
                'e.banco',
                'e.tipo_cuenta',
                'e.numero_cuenta'
            )
            ->limit(6)
            ->get()
            ->map(fn($r) => [...(array)$r, 'tipo' => 'EMPLEADO']);

        $externos = DB::table('dbo.com_funcionario_externo')
            ->where('activo', true)
            ->where(function ($query) use ($like) {
                $query->whereRaw("cedula ILIKE ?", [$like])
                      ->orWhereRaw("UPPER(nombres) ILIKE ?", [$like]);
            })
            ->select('cedula', 'nombres', 'cargo', DB::raw("'Funcionario Externo' as unidad"), 'banco', 'tipo_cuenta', 'numero_cuenta')
            ->limit(4)
            ->get()
            ->map(fn($r) => [...(array)$r, 'tipo' => 'EXTERNO']);

        return response()->json($empleados->concat($externos)->values());
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

    public function detalle(int $id): \Illuminate\Http\JsonResponse
    {
        $s = ComSolicitud::with(['empleado', 'servidores.empleado', 'transportes', 'informe', 'anticipo', 'fichaLiquidacion'])->findOrFail($id);

        $documentos = DB::table('dbo.com_solicitud_documento')
            ->where('solicitud_id', $id)
            ->orderBy('created_at')
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'tipo_doc'       => $d->tipo_doc,
                'nombre_archivo' => $d->nombre_archivo,
                'alfresco_id'    => $d->alfresco_id,
                'created_at'     => $d->created_at,
            ]);

        return response()->json([
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
                'banco'  => $srv->banco,
                'tipo_cuenta'   => $srv->tipo_cuenta,
                'numero_cuenta' => $srv->numero_cuenta,
            ]),
            'transportes'            => $s->transportes,
            'informe'                => $s->informe,
            'anticipo'               => $s->anticipo,
            'ficha_liquidacion'      => $s->fichaLiquidacion,
            'documentos'             => $documentos,
            'created_at'             => $s->created_at,
        ]);
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
}
