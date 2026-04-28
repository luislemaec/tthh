<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Articulo;
use App\Models\Adq\Iva;
use App\Models\Adq\OrdenCompra;
use App\Models\Adq\OrdenCompraDet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdenCompraController extends Controller
{
    public function index(Request $request)
    {
        $query = OrdenCompra::with(['proveedor', 'detalles.articulo'])
            ->orderByDesc('created_at');

        if ($request->estado) {
            $query->where('estado', $request->estado);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $esCompra    = $request->tipo_ingreso !== 'DONACION';
        $esFactura   = $request->tipo_documento === 'FACTURA';

        $request->validate([
            'tipo_ingreso'          => 'required|in:COMPRA,DONACION',
            'proceso_contratacion'  => $esCompra ? ['required', \Illuminate\Validation\Rule::in(DB::table('adq.proceso_contratacion')->where('activo', true)->pluck('nombre')->toArray())] : 'nullable',
            'tipo_documento'        => 'nullable|in:FACTURA,NOTA DE ENTREGA',
            'proveedor_id'          => 'nullable|exists:pgsql.adq.proveedor,id',
            'numero_documento'      => $esFactura ? 'required|string|max:50' : 'nullable|string|max:50',
            'fecha_documento'       => 'nullable|date',
            'observacion'           => 'nullable|string',
            'detalles'              => 'required|array|min:1',
            'detalles.*.articulo_id'     => 'required|exists:pgsql.adq.articulo,id',
            'detalles.*.cantidad'        => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.iva_id'          => 'nullable|exists:pgsql.adq.iva,id',
        ], [
            'numero_documento.required' => 'El número de factura es obligatorio cuando el tipo de documento es FACTURA.',
        ]);

        $anio       = now()->year;
        $ultimo     = DB::table('adq.orden_compra')->where('anio', $anio)->max('numero_secuencial') ?? 0;
        $secuencial = $ultimo + 1;

        $detallesCalc = $this->calcularDetalles($request->detalles);

        $orden = OrdenCompra::create([
            'tipo_ingreso'          => $request->tipo_ingreso,
            'proceso_contratacion'  => $request->proceso_contratacion,
            'tipo_documento'        => $request->tipo_documento,
            'proveedor_id'          => $request->proveedor_id,
            'numero_documento'      => $request->numero_documento,
            'fecha_documento'       => $request->fecha_documento,
            'fecha'                 => $request->fecha_documento ?? now()->toDateString(),
            'estado'                => 'BORRADOR',
            'observacion'           => $request->observacion,
            'subtotal'              => $detallesCalc['subtotal'],
            'iva_valor'             => $detallesCalc['iva_valor'],
            'total'                 => $detallesCalc['total'],
            'usuario_registro'      => $request->user()->id_emp,
            'numero_secuencial'     => $secuencial,
            'anio'                  => $anio,
        ]);

        foreach ($detallesCalc['detalles'] as $det) {
            OrdenCompraDet::create(array_merge($det, ['orden_id' => $orden->id]));
        }

        return response()->json($orden->load(['proveedor', 'detalles.articulo']), 201);
    }

    public function show($id)
    {
        return response()->json(OrdenCompra::with(['proveedor', 'detalles.articulo.iva'])->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $orden = OrdenCompra::findOrFail($id);
        if ($orden->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede editar un ingreso en BORRADOR.'], 422);
        }

        $esCompra  = $request->tipo_ingreso !== 'DONACION';
        $esFactura = $request->tipo_documento === 'FACTURA';
        $request->validate([
            'tipo_ingreso'          => 'required|in:COMPRA,DONACION',
            'proceso_contratacion'  => $esCompra ? ['required', \Illuminate\Validation\Rule::in(DB::table('adq.proceso_contratacion')->where('activo', true)->pluck('nombre')->toArray())] : 'nullable',
            'tipo_documento'        => 'nullable|in:FACTURA,NOTA DE ENTREGA',
            'proveedor_id'          => 'nullable|exists:pgsql.adq.proveedor,id',
            'numero_documento'      => $esFactura ? 'required|string|max:50' : 'nullable|string|max:50',
            'fecha_documento'       => 'nullable|date',
            'detalles'              => 'required|array|min:1',
            'detalles.*.articulo_id'     => 'required|exists:pgsql.adq.articulo,id',
            'detalles.*.cantidad'        => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.iva_id'          => 'nullable|exists:pgsql.adq.iva,id',
        ], [
            'numero_documento.required' => 'El número de factura es obligatorio cuando el tipo de documento es FACTURA.',
        ]);

        $detallesCalc = $this->calcularDetalles($request->detalles);

        $orden->update([
            'tipo_ingreso'         => $request->tipo_ingreso,
            'proceso_contratacion' => $request->proceso_contratacion,
            'tipo_documento'       => $request->tipo_documento,
            'proveedor_id'         => $request->proveedor_id,
            'numero_documento'     => $request->numero_documento,
            'fecha_documento'      => $request->fecha_documento,
            'observacion'          => $request->observacion,
            'subtotal'             => $detallesCalc['subtotal'],
            'iva_valor'            => $detallesCalc['iva_valor'],
            'total'                => $detallesCalc['total'],
        ]);

        $orden->detalles()->delete();
        foreach ($detallesCalc['detalles'] as $det) {
            OrdenCompraDet::create(array_merge($det, ['orden_id' => $orden->id]));
        }

        return response()->json($orden->load(['proveedor', 'detalles.articulo']));
    }

    public function confirmar(Request $request, $id)
    {
        $orden = OrdenCompra::with('detalles')->findOrFail($id);
        if ($orden->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede confirmar un ingreso en BORRADOR.'], 422);
        }

        DB::transaction(function () use ($orden, $request) {
            foreach ($orden->detalles as $det) {
                $articulo       = Articulo::findOrFail($det->articulo_id);
                $precioAnterior = (float) $articulo->precio_unitario;
                $nuevoPrecio    = (float) $det->precio_unitario;
                $precioPromedio = $precioAnterior > 0
                    ? round(($precioAnterior + $nuevoPrecio) / 2, 5)
                    : $nuevoPrecio;

                $stockAntes   = (float) $articulo->stock_actual;
                $stockDespues = $stockAntes + (float) $det->cantidad;

                DB::table('adq.orden_compra_det')
                    ->where('id', $det->id)
                    ->update(['precio_anterior' => $precioAnterior]);

                DB::table('adq.articulo')
                    ->where('id', $det->articulo_id)
                    ->update([
                        'stock_actual'           => DB::raw("stock_actual + {$det->cantidad}"),
                        'stock_maximo_historico' => DB::raw("GREATEST(stock_maximo_historico, stock_actual + {$det->cantidad})"),
                        'precio_unitario'        => $precioPromedio,
                        'updated_at'             => now(),
                    ]);

                DB::table('adq.kardex')->insert([
                    'articulo_id'       => $det->articulo_id,
                    'fecha'             => now(),
                    'tipo_movimiento'   => 'INGRESO',
                    'referencia_tipo'   => 'orden_compra',
                    'referencia_id'     => $orden->id,
                    'referencia_det_id' => $det->id,
                    'numero_documento'  => $orden->numero_documento,
                    'cantidad_entrada'  => $det->cantidad,
                    'cantidad_salida'   => 0,
                    'stock_antes'       => $stockAntes,
                    'stock_despues'     => $stockDespues,
                    'precio_antes'      => $precioAnterior,
                    'precio_despues'    => $precioPromedio,
                    'precio_movimiento' => $det->precio_unitario,
                    'subtotal'          => $det->subtotal ?? 0,
                    'iva_valor'         => $det->iva_valor ?? 0,
                    'total_linea'       => $det->total_linea ?? 0,
                    'usuario'           => $request->user()->id_emp,
                    'observacion'       => $orden->observacion,
                    'created_at'        => now(),
                ]);
            }

            $orden->update([
                'estado'            => 'RECIBIDO',
                'usuario_recepcion' => $request->user()->id_emp,
                'fecha_recepcion'   => now(),
            ]);
        });

        return response()->json($orden->load(['proveedor', 'detalles.articulo']));
    }

    public function reversar(Request $request, $id)
    {
        $orden = OrdenCompra::with('detalles')->findOrFail($id);
        if ($orden->estado !== 'RECIBIDO') {
            return response()->json(['message' => 'Solo se puede reversar un ingreso en estado RECIBIDO.'], 422);
        }

        $request->validate([
            'motivo_reverso' => 'required|string|min:5|max:500',
        ], [
            'motivo_reverso.required' => 'Debe ingresar el motivo del reverso.',
            'motivo_reverso.min'      => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        DB::transaction(function () use ($orden, $request) {
            foreach ($orden->detalles as $det) {
                $articulo       = Articulo::findOrFail($det->articulo_id);
                $precioAnterior = $det->precio_anterior !== null
                    ? (float) $det->precio_anterior
                    : (float) $articulo->precio_unitario;

                $stockAntes   = (float) $articulo->stock_actual;
                $stockDespues = max(0, $stockAntes - (float) $det->cantidad);

                DB::table('adq.articulo')
                    ->where('id', $det->articulo_id)
                    ->update([
                        'stock_actual'    => DB::raw("GREATEST(0, stock_actual - {$det->cantidad})"),
                        'precio_unitario' => $precioAnterior,
                        'updated_at'      => now(),
                    ]);

                DB::table('adq.kardex')->insert([
                    'articulo_id'       => $det->articulo_id,
                    'fecha'             => now(),
                    'tipo_movimiento'   => 'REVERSO_INGRESO',
                    'referencia_tipo'   => 'orden_compra',
                    'referencia_id'     => $orden->id,
                    'referencia_det_id' => $det->id,
                    'numero_documento'  => $orden->numero_documento,
                    'cantidad_entrada'  => 0,
                    'cantidad_salida'   => $det->cantidad,
                    'stock_antes'       => $stockAntes,
                    'stock_despues'     => $stockDespues,
                    'precio_antes'      => (float) $articulo->precio_unitario,
                    'precio_despues'    => $precioAnterior,
                    'precio_movimiento' => $det->precio_unitario,
                    'subtotal'          => $det->subtotal ?? 0,
                    'iva_valor'         => $det->iva_valor ?? 0,
                    'total_linea'       => $det->total_linea ?? 0,
                    'usuario'           => $request->user()->id_emp,
                    'observacion'       => $request->motivo_reverso,
                    'created_at'        => now(),
                ]);
            }

            $orden->update([
                'estado'            => 'BORRADOR',
                'usuario_recepcion' => null,
                'fecha_recepcion'   => null,
                'motivo_reverso'    => $request->motivo_reverso,
                'usuario_reverso'   => $request->user()->id_emp,
                'fecha_reverso'     => now(),
            ]);
        });

        return response()->json($orden->load(['proveedor', 'detalles.articulo']));
    }

    public function pdf($id)
    {
        $orden = OrdenCompra::with(['proveedor', 'detalles.articulo'])->findOrFail($id);
        if ($orden->estado !== 'RECIBIDO') {
            return response()->json(['message' => 'El PDF solo está disponible para ingresos confirmados.'], 422);
        }
        $pdf = Pdf::loadView('reportes.ingreso_bodega', compact('orden'))
            ->setPaper('letter', 'portrait');
        return $pdf->download("ingreso-bodega-{$orden->id}.pdf");
    }

    public function destroy($id)
    {
        $orden = OrdenCompra::findOrFail($id);
        if ($orden->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede eliminar un ingreso en BORRADOR.'], 422);
        }
        $orden->delete();
        return response()->json(['message' => 'Ingreso eliminado.']);
    }

    private function calcularDetalles(array $detalles): array
    {
        $subtotalTotal = 0;
        $ivaTotal      = 0;
        $items         = [];

        foreach ($detalles as $det) {
            $ivaPct   = 0;
            $ivaId    = $det['iva_id'] ?? null;
            if ($ivaId) {
                $iva    = Iva::find($ivaId);
                $ivaPct = $iva ? (float) $iva->porcentaje : 0;
            }

            $precioIngresado = (float) $det['precio_unitario'];
            $incluyeIva      = !empty($det['precio_incluye_iva']);

            if ($incluyeIva && $ivaPct > 0) {
                // Total exacto de la factura; subtotal y IVA se calculan desde el total
                $totalCents   = (int) round((float) $det['cantidad'] * $precioIngresado * 100);
                $subtotalCents = (int) round($totalCents / (1 + $ivaPct / 100));
                $ivaCents     = $totalCents - $subtotalCents;
                $subtotal     = $subtotalCents / 100;
                $ivaValor     = $ivaCents / 100;
                $totalLinea   = $totalCents / 100;
                $precioSinIva = round($precioIngresado / (1 + $ivaPct / 100), 5);
            } else {
                $subCents     = (int) round((float) $det['cantidad'] * $precioIngresado * 100);
                $ivaCents     = (int) round($subCents * $ivaPct / 100);
                $subtotal     = $subCents / 100;
                $ivaValor     = $ivaCents / 100;
                $totalLinea   = ($subCents + $ivaCents) / 100;
                $precioSinIva = $precioIngresado;
            }

            $subtotalTotal += $subtotal;
            $ivaTotal      += $ivaValor;

            $items[] = [
                'articulo_id'    => $det['articulo_id'],
                'cantidad'       => $det['cantidad'],
                'precio_unitario' => $precioSinIva,
                'iva_id'         => $ivaId,
                'iva_porcentaje' => $ivaPct,
                'subtotal'       => $subtotal,
                'iva_valor'      => $ivaValor,
                'total_linea'    => $totalLinea,
            ];
        }

        return [
            'detalles' => $items,
            'subtotal' => round($subtotalTotal, 2),
            'iva_valor' => round($ivaTotal, 2),
            'total'    => round($subtotalTotal + $ivaTotal, 2),
        ];
    }
}
