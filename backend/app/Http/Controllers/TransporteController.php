<?php
namespace App\Http\Controllers;

use App\Models\Adq\Proveedor;
use App\Services\AuditoriaService;
use App\Models\Transporte\Vehiculo;
use App\Models\Transporte\Mantenimiento;
use App\Models\Transporte\MantenimientoActividad;
use App\Models\Transporte\PlanPreventivoDet;
use App\Models\Transporte\TipoMantenimiento;
use App\Models\Transporte\SolicitudMov;
use App\Models\Empleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class TransporteController extends Controller
{
    private function emp(Request $request)
    {
        return $request->user();
    }

    private function esTransporte(Request $request): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $this->emp($request)->id_emp)
            ->where('r.descripcion', 'TRANSPORTE')
            ->exists();
    }

    private function esConductor(Request $request): bool
    {
        return DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $this->emp($request)->id_emp)
            ->where('r.descripcion', 'CONDUCTOR')
            ->exists();
    }

    // ─── VEHÍCULOS ────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        if (!$this->esTransporte($request) && !$this->esConductor($request)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }
        $vehiculos = Vehiculo::orderBy('placa')->get();

        $planes = DB::table('dbo.trans_plan_preventivo_cab')
            ->where('estado', 'ACTIVO')
            ->get()
            ->groupBy('vehiculo_id');

        // km_hito es un hito ABSOLUTO y único (ej. "a los 170.000 km toca este mantenimiento",
        // una sola vez), no un intervalo que se repite. Un plan que ya tuvo algún mantenimiento
        // FINALIZADO se considera cumplido y deja de generar alerta.
        $realizados = DB::table('dbo.trans_mantenimiento')
            ->whereNotNull('plan_preventivo_id')
            ->where('estado', 'FINALIZADO')
            ->pluck('plan_preventivo_id')
            ->unique();

        $vehiculos->each(function ($v) use ($planes, $realizados) {
            $estados = $planes->get($v->id, collect())
                ->reject(fn($p) => $realizados->contains($p->id))
                ->map(function ($p) use ($v) {
                    $vencido = $p->km_hito > 0 && $v->kilometraje_actual >= $p->km_hito;
                    $proximo = !$vencido && $p->km_hito > 0 && $v->kilometraje_actual >= $p->km_hito * 0.9;

                    return [
                        'plan_id'       => $p->id,
                        'nombre'        => $p->nombre,
                        'km_hito'       => $p->km_hito,
                        'km_recorridos' => $v->kilometraje_actual,
                        'vencido'       => $vencido,
                        'proximo'       => $proximo,
                    ];
                })->values();

            // Un mismo vehículo puede tener varios planes: unos ya vencidos y otros recién
            // próximos (ej. hito de 50.000 vencido y el de 170.000 próximo a la vez). Por eso
            // NO son mutuamente excluyentes — si no, la tarjeta resumen y los badges de la
            // lista quedaban contando cosas distintas.
            $v->planes_estado         = $estados;
            $v->mantenimiento_vencido = $estados->contains('vencido', true);
            $v->mantenimiento_proximo = $estados->contains('proximo', true);
        });

        return response()->json($vehiculos);
    }

    public function store(Request $request)
    {
        if (!$this->esTransporte($request)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }
        $request->validate([
            'placa'              => 'required|string|max:10|unique:pgsql.dbo.trans_vehiculo,placa',
            'marca'              => 'required|string|max:50',
            'modelo'             => 'required|string|max:50',
            'anio'               => 'required|integer|min:1990|max:2100',
            'chasis'             => 'nullable|string|max:50',
            'color'              => 'nullable|string|max:30',
            'numero_motor'       => 'nullable|string|max:50',
            'kilometraje_actual' => 'required|integer|min:0',
        ]);

        $vehiculo = Vehiculo::create($request->only([
            'placa', 'marca', 'modelo', 'anio', 'chasis', 'color', 'numero_motor', 'kilometraje_actual',
        ]));

        AuditoriaService::log('dbo.trans_vehiculo', $vehiculo->id, 'CREAR_VEHICULO',
            null,
            ['placa' => $vehiculo->placa, 'kilometraje_actual' => $vehiculo->kilometraje_actual],
            $request, "Vehículo {$vehiculo->placa} creado con {$vehiculo->kilometraje_actual} km");

        return response()->json($vehiculo, 201);
    }

    public function update(Request $request, $id)
    {
        if (!$this->esTransporte($request)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }
        $vehiculo = Vehiculo::findOrFail($id);
        $anterior = $vehiculo->only(['kilometraje_actual', 'estado']);

        $request->validate([
            'placa'              => 'required|string|max:10|unique:pgsql.dbo.trans_vehiculo,placa,' . $id,
            'marca'              => 'required|string|max:50',
            'modelo'             => 'required|string|max:50',
            'anio'               => 'required|integer|min:1990|max:2100',
            'chasis'             => 'nullable|string|max:50',
            'color'              => 'nullable|string|max:30',
            'numero_motor'       => 'nullable|string|max:50',
            // No puede bajarse manualmente: el contador es acumulativo (hoja de ruta / mantenimiento).
            // Solo se permite corregirlo hacia arriba (ej. dato inicial mal digitado por debajo del real).
            'kilometraje_actual' => 'required|integer|min:' . $vehiculo->kilometraje_actual,
            'estado'             => 'required|in:ACTIVO,INACTIVO,MANTENIMIENTO',
        ], [
            'kilometraje_actual.min' => 'El kilometraje no puede ser menor al actual (' . $vehiculo->kilometraje_actual . '). El contador solo se corrige hacia arriba.',
        ]);

        $vehiculo->update($request->only([
            'placa', 'marca', 'modelo', 'anio', 'chasis', 'color', 'numero_motor', 'kilometraje_actual', 'estado',
        ]));

        if ($anterior['kilometraje_actual'] != $vehiculo->kilometraje_actual || $anterior['estado'] != $vehiculo->estado) {
            AuditoriaService::log('dbo.trans_vehiculo', $vehiculo->id, 'ACTUALIZAR_VEHICULO',
                $anterior,
                $vehiculo->only(['kilometraje_actual', 'estado']),
                $request, "Vehículo {$vehiculo->placa} actualizado");
        }

        return response()->json($vehiculo);
    }

    // ─── MANTENIMIENTO ────────────────────────────────────────────────────────

    public function indexMtto(Request $request)
    {
        if (!$this->esTransporte($request) && !$this->esConductor($request)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }
        // El vehículo es un recurso compartido entre conductores: todos deben ver el mismo
        // listado (no solo lo que cada uno solicitó), para saber si ya hay un mantenimiento
        // en curso de ese vehículo antes de pedir otro.
        $query = Mantenimiento::with(['vehiculo', 'conductor', 'responsable', 'actividades', 'tipoMantenimiento', 'tallerRel'])
            ->orderBy('created_at', 'desc');

        return response()->json($query->get());
    }

    public function storeMtto(Request $request)
    {
        if (!$this->esTransporte($request) && !$this->esConductor($request)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }
        $request->validate([
            'vehiculo_id'           => 'required|exists:pgsql.dbo.trans_vehiculo,id',
            'tipo_mantenimiento_id' => 'required|exists:pgsql.dbo.trans_tipo_mantenimiento,id',
            'descripcion'           => 'nullable|string',
            'plan_preventivo_id'    => 'nullable|exists:pgsql.dbo.trans_plan_preventivo_cab,id',
            'actividades_correctivas'              => 'nullable|array',
            'actividades_correctivas.*.actividad'  => 'required_with:actividades_correctivas|string',
        ]);

        // Km actual ya no lo digita el conductor: siempre es el kilometraje_actual real del
        // vehículo, para que quede consistente con el mismo contador que usa movilización.
        $vehiculo = Vehiculo::findOrFail($request->vehiculo_id);

        // Un vehículo solo puede tener UN mantenimiento abierto a la vez. Evita que se olviden
        // de finalizar uno y se genere otro encima (o que dos conductores pidan lo mismo sin verse).
        $abierto = Mantenimiento::where('vehiculo_id', $request->vehiculo_id)
            ->whereNotIn('estado', ['FINALIZADO', 'NEGADO'])
            ->first();

        if ($abierto) {
            return response()->json([
                'message' => "Este vehículo ya tiene un mantenimiento en curso (#{$abierto->id}, {$abierto->tipo}, estado: {$abierto->estado}). Debe finalizarlo o negarlo antes de registrar uno nuevo.",
            ], 422);
        }

        $tipo = TipoMantenimiento::findOrFail($request->tipo_mantenimiento_id);

        $esPreventivo = str_contains($tipo->nombre, 'PREVENTIVO');
        $esCorrectivo = str_contains($tipo->nombre, 'CORRECTIVO');

        if ($esPreventivo && !$request->plan_preventivo_id) {
            return response()->json(['message' => 'Debe seleccionar un plan preventivo para este tipo de mantenimiento.'], 422);
        }

        if ($request->plan_preventivo_id) {
            $planPertenece = DB::table('dbo.trans_plan_preventivo_cab')
                ->where('id', $request->plan_preventivo_id)
                ->where('vehiculo_id', $request->vehiculo_id)
                ->exists();

            if (!$planPertenece) {
                return response()->json(['message' => 'El plan preventivo seleccionado no corresponde a este vehículo.'], 422);
            }

            // km_hito es un hito único: si este plan ya tuvo un mantenimiento FINALIZADO,
            // no se puede volver a ejecutar (para eso se crean planes separados por cada hito).
            $yaEjecutado = Mantenimiento::where('plan_preventivo_id', $request->plan_preventivo_id)
                ->where('estado', 'FINALIZADO')
                ->exists();

            if ($yaEjecutado) {
                return response()->json(['message' => 'Este plan preventivo ya fue ejecutado anteriormente. No se puede repetir un hito ya cumplido.'], 422);
            }
        }

        $m = Mantenimiento::create([
            'vehiculo_id'           => $request->vehiculo_id,
            'tipo'                  => $tipo->nombre,
            'tipo_mantenimiento_id' => $request->tipo_mantenimiento_id,
            'km_actual'             => $vehiculo->kilometraje_actual,
            'descripcion'           => $request->descripcion ?? $tipo->nombre,
            'plan_preventivo_id'    => $request->plan_preventivo_id,
            'id_emp_conductor'      => $this->emp($request)->id_emp,
            'estado'                => 'PENDIENTE',
        ]);

        if ($esPreventivo && $request->plan_preventivo_id) {
            $detalles = PlanPreventivoDet::where('cab_id', $request->plan_preventivo_id)->orderBy('orden')->get();
            foreach ($detalles as $det) {
                MantenimientoActividad::create([
                    'mantenimiento_id' => $m->id,
                    'tipo'             => 'PREVENTIVO',
                    'tipo_actividad'   => $det->tipo_actividad,
                    'actividad'        => $det->actividad,
                    'orden'            => $det->orden,
                    'cantidad'         => $det->cantidad,
                ]);
            }
        }

        if ($esCorrectivo && $request->actividades_correctivas) {
            foreach ($request->actividades_correctivas as $i => $act) {
                MantenimientoActividad::create([
                    'mantenimiento_id' => $m->id,
                    'tipo'             => 'CORRECTIVO',
                    'actividad'        => $act['actividad'],
                    'orden'            => $i + 1,
                ]);
            }
        }

        return response()->json($m->load(['vehiculo', 'conductor', 'actividades', 'tipoMantenimiento']), 201);
    }

    public function updateMtto(Request $request, $id)
    {
        if (!$this->esTransporte($request)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }
        $m = Mantenimiento::findOrFail($id);

        $accion = $request->input('accion');

        if ($accion === 'orden') {
            $request->validate([
                'taller_id'   => 'required|exists:pgsql.adq.proveedor,id',
                'fecha_orden' => 'required|date',
            ]);

            $tallerNombre = Proveedor::findOrFail($request->taller_id)->nombre;

            $anio   = date('Y', strtotime($request->fecha_orden));
            $maxNum = Mantenimiento::where('tipo_mantenimiento_id', $m->tipo_mantenimiento_id)
                ->whereNotNull('numero_orden')
                ->whereRaw("EXTRACT(YEAR FROM fecha_orden) = ?", [$anio])
                ->max(DB::raw("CAST(SPLIT_PART(numero_orden, '-', 1) AS INTEGER)"));
            $numero_orden = str_pad(($maxNum ?? 0) + 1, 4, '0', STR_PAD_LEFT) . '-' . $anio;

            $m->update([
                'taller_id'              => $request->taller_id,
                'taller'                 => $tallerNombre,
                'numero_orden'           => $numero_orden,
                'fecha_orden'            => $request->fecha_orden,
                'observacion_responsable'=> $request->observacion_responsable,
                'id_emp_responsable'     => $this->emp($request)->id_emp,
                'estado'                 => 'ORDEN_GENERADA',
            ]);

            AuditoriaService::log('dbo.trans_mantenimiento', $m->id, 'ORDEN_TRABAJO',
                ['estado' => 'PENDIENTE'],
                ['estado' => 'ORDEN_GENERADA', 'numero_orden' => $numero_orden, 'taller' => $tallerNombre],
                $request, "Orden de trabajo #{$numero_orden} generada");

        } elseif ($accion === 'negar') {
            $m->update([
                'estado'           => 'NEGADO',
                'motivo_negacion'  => $request->motivo_negacion,
                'fecha_negacion'   => now(),
                'usuario_negacion' => $this->emp($request)->id_emp,
            ]);

            AuditoriaService::log('dbo.trans_mantenimiento', $m->id, 'NEGAR_MANT',
                ['estado' => 'PENDIENTE'],
                ['estado' => 'NEGADO', 'motivo' => $request->motivo_negacion],
                $request, "Negación de mantenimiento #{$m->id}");

        } elseif ($accion === 'en_taller') {
            $m->update(['estado' => 'EN_TALLER']);

            AuditoriaService::log('dbo.trans_mantenimiento', $m->id, 'EN_TALLER',
                ['estado' => 'ORDEN_GENERADA'],
                ['estado' => 'EN_TALLER'],
                $request, "Mantenimiento #{$m->id} en taller");

        } elseif ($accion === 'finalizar') {
            // El km de finalización es obligatorio para PREVENTIVO: sin él no hay forma de
            // saber desde cuándo contar el próximo hito de ese plan. En CORRECTIVO sigue
            // siendo opcional (ese flujo ya tiene su propio registro de reparación).
            $esPreventivo = str_contains($m->tipo, 'PREVENTIVO');
            $kmVehiculoActual = $m->vehiculo->kilometraje_actual ?? 0;

            $rules = ['fecha_finalizacion' => 'required|date'];
            $rules['km_finalizacion'] = ($esPreventivo ? 'required' : 'nullable') . '|integer|min:' . $kmVehiculoActual;

            $request->validate($rules, [
                'km_finalizacion.required' => 'Debe indicar el kilometraje del vehículo para finalizar un mantenimiento preventivo.',
                'km_finalizacion.min'      => 'El kilometraje no puede ser menor al km actual del vehículo (' . $kmVehiculoActual . ').',
            ]);

            $m->update([
                'estado'             => 'FINALIZADO',
                'fecha_finalizacion' => $request->fecha_finalizacion,
                'observacion_responsable' => $request->observacion_responsable ?? $m->observacion_responsable,
                'km_finalizacion'    => $request->km_finalizacion ?? $m->km_finalizacion,
            ]);
            // Acumular km del vehículo
            if ($request->filled('km_finalizacion')) {
                $m->vehiculo->update(['kilometraje_actual' => $request->km_finalizacion]);
            }

            AuditoriaService::log('dbo.trans_mantenimiento', $m->id, 'FINALIZAR_MANT',
                ['estado' => 'EN_TALLER', 'kilometraje_actual' => $kmVehiculoActual],
                ['estado' => 'FINALIZADO', 'fecha_finalizacion' => $request->fecha_finalizacion, 'km_finalizacion' => $request->km_finalizacion],
                $request, "Finalización de mantenimiento #{$m->id}");
        }

        return response()->json($m->load(['vehiculo', 'conductor', 'responsable']));
    }

    public function pdfMtto(Request $request, $id)
    {
        if (!$this->esTransporte($request) && !$this->esConductor($request)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }
        $m = Mantenimiento::with(['vehiculo', 'conductor', 'responsable', 'actividades'])->findOrFail($id);
        $logo = $this->logoBase64();

        $pdf = Pdf::loadView('reportes.trans_orden_trabajo', compact('m', 'logo'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('orden_trabajo_' . $m->numero_orden . '.pdf');
    }

    // ─── MOVILIZACIÓN ─────────────────────────────────────────────────────────

    public function indexMov(Request $request)
    {
        $emp = $this->emp($request);
        $esTransporte = $this->esTransporte($request);
        $esConductor  = $this->esConductor($request);

        $query = SolicitudMov::with(['solicitante', 'vehiculo', 'conductor'])
            ->orderBy('fecha_movilizacion', 'desc');

        if ($esTransporte) {
            // Ve todas
        } elseif ($esConductor) {
            $query->where('id_emp_conductor', $emp->id_emp);
        } else {
            // Empleado autorizado: solo las suyas
            $query->where('id_emp_solicitante', $emp->id_emp);
        }

        return response()->json($query->get());
    }

    public function storeMov(Request $request)
    {
        $emp = $this->emp($request);

        if (!$emp->puede_solicitar_vehiculo && !$this->esTransporte($request)) {
            return response()->json(['message' => 'No tiene permiso para solicitar vehículos.'], 403);
        }

        $request->validate([
            'motivo'              => 'required|string',
            'fecha_movilizacion'  => 'required|date',
            'hora_salida'         => 'required',
            'hora_retorno'        => 'required',
            'lugar_salida'        => 'nullable|string|max:100',
            'lugar_destino'       => 'required|string|max:200',
            'direccion_salida'    => 'nullable|string|max:200',
            'direccion_destino'   => 'nullable|string|max:200',
            'pasajeros'           => 'nullable|string',
            'num_personas'        => 'required|integer|min:1',
        ]);

        $s = SolicitudMov::create(array_merge(
            $request->only(['motivo', 'fecha_movilizacion', 'hora_salida', 'hora_retorno',
                            'lugar_salida', 'lugar_destino', 'direccion_salida',
                            'direccion_destino', 'pasajeros', 'num_personas']),
            ['id_emp_solicitante' => $emp->id_emp, 'estado' => 'PENDIENTE']
        ));

        return response()->json($s->load('solicitante'), 201);
    }

    public function updateMov(Request $request, $id)
    {
        $s = SolicitudMov::findOrFail($id);
        $accion = $request->input('accion');

        if (in_array($accion, ['aprobar', 'negar']) && !$this->esTransporte($request)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }
        if ($accion === 'hoja_ruta' && $s->id_emp_conductor !== $this->emp($request)->id_emp && !$this->esTransporte($request)) {
            abort(403, 'Solo el conductor asignado puede completar la hoja de ruta.');
        }

        if ($accion === 'aprobar') {
            $request->validate([
                'vehiculo_id'      => 'required|exists:pgsql.dbo.trans_vehiculo,id',
                'id_emp_conductor' => 'required|exists:pgsql.dbo.ad_empleado,id_emp',
            ]);

            // Verificar disponibilidad del vehículo en ese rango
            $conflicto = SolicitudMov::where('vehiculo_id', $request->vehiculo_id)
                ->where('fecha_movilizacion', $s->fecha_movilizacion)
                ->where('estado', 'APROBADO')
                ->where('id', '!=', $id)
                ->where(function ($q) use ($s) {
                    $q->whereBetween('hora_salida', [$s->hora_salida, $s->hora_retorno])
                      ->orWhereBetween('hora_retorno', [$s->hora_salida, $s->hora_retorno]);
                })->exists();

            if ($conflicto) {
                return response()->json(['message' => 'El vehículo ya tiene una asignación en ese horario.'], 422);
            }

            $s->update([
                'estado'             => 'APROBADO',
                'vehiculo_id'        => $request->vehiculo_id,
                'id_emp_conductor'   => $request->id_emp_conductor,
                'observacion'        => $request->observacion,
                'id_emp_responsable' => $this->emp($request)->id_emp,
                'fecha_aprobacion'   => now(),
            ]);

            AuditoriaService::log('dbo.trans_solicitud_mov', $s->id, 'APROBAR_MOV',
                ['estado' => 'PENDIENTE'],
                ['estado' => 'APROBADO', 'vehiculo_id' => $request->vehiculo_id, 'id_emp_conductor' => $request->id_emp_conductor],
                $request, "Aprobación de movilización #{$s->id}: {$s->motivo}");

        } elseif ($accion === 'negar') {
            $s->update([
                'estado'             => 'NEGADO',
                'observacion'        => $request->observacion,
                'id_emp_responsable' => $this->emp($request)->id_emp,
                'fecha_negacion'     => now(),
            ]);

            AuditoriaService::log('dbo.trans_solicitud_mov', $s->id, 'NEGAR_MOV',
                ['estado' => 'PENDIENTE'],
                ['estado' => 'NEGADO', 'observacion' => $request->observacion],
                $request, "Negación de movilización #{$s->id}: {$s->motivo}");

        } elseif ($accion === 'hoja_ruta') {
            // Km de salida ya NO se recibe del cliente: siempre es el kilometraje_actual
            // del vehículo en este momento, para que el contador acumule viaje tras viaje
            // sin depender de que el conductor lo digite correctamente.
            $kmSalida = $s->vehiculo->kilometraje_actual ?? 0;

            $request->validate(['km_retorno' => 'required|integer']);

            // El km de salida mostrado en el frontend puede haber quedado desactualizado si otro
            // viaje/mantenimiento de este mismo vehículo se completó mientras el modal estaba abierto.
            // Se devuelve el valor real para que el frontend se autocorrija en vez de solo fallar.
            if ($request->km_retorno < $kmSalida) {
                return response()->json([
                    'message'          => 'El kilometraje del vehículo cambió mientras completabas este formulario. Se actualizó el km de salida, verifica el km de retorno.',
                    'km_salida_actual' => $kmSalida,
                ], 422);
            }

            $s->update([
                'estado'               => 'COMPLETADO',
                'km_salida'            => $kmSalida,
                'km_retorno'           => $request->km_retorno,
                'hoja_ruta_observacion'=> $request->hoja_ruta_observacion,
                'fecha_completado'     => now(),
            ]);
            // Acumular km del vehículo
            if ($s->vehiculo_id) {
                $placaVehiculo = $s->vehiculo->placa;
                $s->vehiculo->update(['kilometraje_actual' => $request->km_retorno]);

                AuditoriaService::log('dbo.trans_vehiculo', $s->vehiculo_id, 'HOJA_RUTA',
                    ['kilometraje_actual' => $kmSalida],
                    ['kilometraje_actual' => $request->km_retorno],
                    $request, "Hoja de ruta solicitud #{$s->id}, vehículo {$placaVehiculo}: {$kmSalida} → {$request->km_retorno} km");
            }
        }

        return response()->json($s->load(['solicitante', 'vehiculo', 'conductor']));
    }

    public function notificacionesPendientes(Request $request)
    {
        if (!$this->esTransporte($request)) {
            return response()->json(['pendientes' => 0]);
        }

        $pendientes = DB::table('dbo.trans_solicitud_mov')
            ->where('estado', 'PENDIENTE')
            ->select('id', 'id_emp_solicitante', 'fecha_movilizacion', 'lugar_destino', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $ultima = DB::table('dbo.trans_solicitud_mov')
            ->where('estado', 'PENDIENTE')
            ->max('created_at');

        return response()->json([
            'pendientes' => $pendientes->count(),
            'ultima_at'  => $ultima,
            'items'      => $pendientes->take(5)->map(fn($s) => [
                'id'                  => $s->id,
                'fecha_movilizacion'  => $s->fecha_movilizacion,
                'lugar_destino'       => $s->lugar_destino,
            ]),
        ]);
    }

    public function pdfMov($id)
    {
        $s = SolicitudMov::with(['solicitante', 'vehiculo', 'conductor'])->findOrFail($id);
        $logo = $this->logoBase64();

        $pdf = Pdf::loadView('reportes.trans_orden_movilizacion', compact('s', 'logo'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('orden_movilizacion_' . $s->id . '.pdf');
    }

    // ─── CONDUCTORES (para selectores en frontend) ───────────────────────────

    public function conductores(Request $request)
    {
        if (!$this->esTransporte($request)) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }
        $ids = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('r.descripcion', 'CONDUCTOR')
            ->pluck('ur.id_emp');

        return response()->json(
            Empleado::whereIn('id_emp', $ids)
                ->where('estado', 'ACTIVO')
                ->orderBy('apellido_emp')
                ->get(['id_emp', 'apellido_emp', 'nombre_emp'])
        );
    }

    private function logoBase64(): ?string
    {
        $path = public_path('logo.png');
        return file_exists($path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($path)) : null;
    }
}
