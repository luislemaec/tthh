<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Articulo;
use App\Models\Adq\Egreso;
use App\Models\Adq\EgresoDet;
use App\Models\Adq\Iva;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EgresoController extends Controller
{
    public function index(Request $request)
    {
        $query = Egreso::orderByDesc('created_at');
        if ($request->estado) {
            $query->where('estado', $request->estado);
        }
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'direccion'       => 'required|string|max:200',
            'empleado_id'     => 'required|string|max:20',
            'empleado_nombre' => 'required|string|max:200',
            'observacion'     => 'nullable|string',
            'detalles'        => 'required|array|min:1',
            'detalles.*.articulo_id' => 'required|exists:pgsql.adq.articulo,id',
            'detalles.*.cantidad'    => 'required|numeric|min:0.01',
        ]);

        $anio       = now()->year;
        $ultimo     = DB::table('adq.egreso')->where('anio', $anio)->max('numero_secuencial') ?? 0;
        $secuencial = $ultimo + 1;

        $calc = $this->calcularDetalles($request->detalles);

        $egreso = Egreso::create([
            'numero_secuencial' => $secuencial,
            'anio'              => $anio,
            'direccion'         => $request->direccion,
            'empleado_id'       => $request->empleado_id,
            'empleado_nombre'   => $request->empleado_nombre,
            'observacion'       => $request->observacion,
            'estado'            => 'BORRADOR',
            'subtotal'          => $calc['subtotal'],
            'iva_valor'         => $calc['iva_valor'],
            'total'             => $calc['total'],
            'usuario_registro'  => $request->user()->id_emp,
        ]);

        foreach ($calc['detalles'] as $det) {
            EgresoDet::create(array_merge($det, ['egreso_id' => $egreso->id]));
        }

        return response()->json($egreso->load('detalles.articulo'), 201);
    }

    public function show($id)
    {
        return response()->json(Egreso::with(['detalles.articulo.iva'])->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $egreso = Egreso::findOrFail($id);
        if ($egreso->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede editar un egreso en BORRADOR.'], 422);
        }

        $request->validate([
            'direccion'       => 'required|string|max:200',
            'empleado_id'     => 'required|string|max:20',
            'empleado_nombre' => 'required|string|max:200',
            'observacion'     => 'nullable|string',
            'detalles'        => 'required|array|min:1',
            'detalles.*.articulo_id' => 'required|exists:pgsql.adq.articulo,id',
            'detalles.*.cantidad'    => 'required|numeric|min:0.01',
        ]);

        $calc = $this->calcularDetalles($request->detalles);

        $egreso->update([
            'direccion'       => $request->direccion,
            'empleado_id'     => $request->empleado_id,
            'empleado_nombre' => $request->empleado_nombre,
            'observacion'     => $request->observacion,
            'subtotal'        => $calc['subtotal'],
            'iva_valor'       => $calc['iva_valor'],
            'total'           => $calc['total'],
        ]);

        $egreso->detalles()->delete();
        foreach ($calc['detalles'] as $det) {
            EgresoDet::create(array_merge($det, ['egreso_id' => $egreso->id]));
        }

        return response()->json($egreso->load('detalles.articulo'));
    }

    public function confirmar(Request $request, $id)
    {
        $egreso = Egreso::with('detalles')->findOrFail($id);
        if ($egreso->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede confirmar un egreso en BORRADOR.'], 422);
        }

        // Verificar stock disponible para todos los artículos
        foreach ($egreso->detalles as $det) {
            $articulo = Articulo::findOrFail($det->articulo_id);
            if ((float) $articulo->stock_actual < (float) $det->cantidad) {
                return response()->json([
                    'message' => "Stock insuficiente para \"{$articulo->nombre}\". Disponible: {$articulo->stock_actual}, Solicitado: {$det->cantidad}.",
                ], 422);
            }
        }

        DB::transaction(function () use ($egreso, $request) {
            $subtotalTotal = 0;
            $ivaTotal      = 0;

            foreach ($egreso->detalles as $det) {
                $articulo       = Articulo::findOrFail($det->articulo_id);
                $precioAnterior = (float) $articulo->precio_unitario;
                $ivaPct         = 0;

                if ($articulo->iva_id) {
                    $iva    = Iva::find($articulo->iva_id);
                    $ivaPct = $iva ? (float) $iva->porcentaje : 0;
                }

                $subCents   = (int) round((float) $det->cantidad * $precioAnterior * 100);
                $ivaCents   = (int) round($subCents * $ivaPct / 100);
                $subtotal   = $subCents / 100;
                $ivaValor   = $ivaCents / 100;
                $totalLinea = ($subCents + $ivaCents) / 100;

                $subtotalTotal += $subtotal;
                $ivaTotal      += $ivaValor;

                DB::table('adq.egreso_det')->where('id', $det->id)->update([
                    'precio_unitario' => $precioAnterior,
                    'precio_anterior' => $precioAnterior,
                    'iva_id'          => $articulo->iva_id,
                    'iva_porcentaje'  => $ivaPct,
                    'subtotal'        => $subtotal,
                    'iva_valor'       => $ivaValor,
                    'total_linea'     => $totalLinea,
                    'updated_at'      => now(),
                ]);

                $stockAntes  = (float) $articulo->stock_actual;
                $nuevoStock  = max(0, $stockAntes - (float) $det->cantidad);
                $nuevoPrecio = $nuevoStock == 0 ? 0 : $precioAnterior;

                DB::table('adq.articulo')->where('id', $det->articulo_id)->update([
                    'stock_actual'    => $nuevoStock,
                    'precio_unitario' => $nuevoPrecio,
                    'updated_at'      => now(),
                ]);

                DB::table('adq.kardex')->insert([
                    'articulo_id'       => $det->articulo_id,
                    'fecha'             => now(),
                    'tipo_movimiento'   => 'EGRESO',
                    'referencia_tipo'   => 'egreso',
                    'referencia_id'     => $egreso->id,
                    'referencia_det_id' => $det->id,
                    'numero_documento'  => null,
                    'cantidad_entrada'  => 0,
                    'cantidad_salida'   => $det->cantidad,
                    'stock_antes'       => $stockAntes,
                    'stock_despues'     => $nuevoStock,
                    'precio_antes'      => $precioAnterior,
                    'precio_despues'    => $nuevoPrecio,
                    'precio_movimiento' => $precioAnterior,
                    'subtotal'          => $subtotal,
                    'iva_valor'         => $ivaValor,
                    'total_linea'       => $totalLinea,
                    'valor_saldo'       => round($nuevoStock * $nuevoPrecio, 2),
                    'usuario'           => $request->user()->id_emp,
                    'observacion'       => $egreso->observacion,
                    'created_at'        => now(),
                ]);
            }

            $egreso->update([
                'subtotal'         => round($subtotalTotal, 2),
                'iva_valor'        => round($ivaTotal, 2),
                'total'            => round($subtotalTotal + $ivaTotal, 2),
                'estado'           => 'DESPACHADO',
                'usuario_despacho' => $request->user()->id_emp,
                'fecha_despacho'   => now(),
            ]);
        });

        AuditoriaService::log('adq.egreso', $egreso->id, 'CONFIRMAR_EGRESO',
            ['estado' => 'BORRADOR'],
            ['estado' => 'DESPACHADO', 'total' => $egreso->total, 'empleado_nombre' => $egreso->empleado_nombre],
            $request, "Confirmación de egreso de bodega #{$egreso->numero_secuencial}/{$egreso->anio}");

        return response()->json($egreso->load('detalles.articulo'));
    }

    public function reversar(Request $request, $id)
    {
        $egreso = Egreso::with('detalles')->findOrFail($id);
        if ($egreso->estado !== 'DESPACHADO') {
            return response()->json(['message' => 'Solo se puede reversar un egreso en estado DESPACHADO.'], 422);
        }

        $request->validate([
            'motivo_reverso' => 'required|string|min:5|max:500',
        ], [
            'motivo_reverso.required' => 'Debe ingresar el motivo del reverso.',
            'motivo_reverso.min'      => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        DB::transaction(function () use ($egreso, $request) {
            foreach ($egreso->detalles as $det) {
                $articulo       = Articulo::findOrFail($det->articulo_id);
                $stockAntes     = (float) $articulo->stock_actual;
                $nuevoStock     = $stockAntes + (float) $det->cantidad;
                $precioAnterior = $det->precio_anterior !== null ? (float) $det->precio_anterior : 0;

                // Restaurar precio solo si el artículo quedó en 0 (el egreso lo puso en 0)
                $nuevoPrecio = ((float) $articulo->precio_unitario == 0 && $precioAnterior > 0)
                    ? $precioAnterior
                    : (float) $articulo->precio_unitario;

                DB::table('adq.articulo')->where('id', $det->articulo_id)->update([
                    'stock_actual'    => $nuevoStock,
                    'precio_unitario' => $nuevoPrecio,
                    'updated_at'      => now(),
                ]);

                DB::table('adq.kardex')->insert([
                    'articulo_id'       => $det->articulo_id,
                    'fecha'             => now(),
                    'tipo_movimiento'   => 'REVERSO_EGRESO',
                    'referencia_tipo'   => 'egreso',
                    'referencia_id'     => $egreso->id,
                    'referencia_det_id' => $det->id,
                    'numero_documento'  => null,
                    'cantidad_entrada'  => $det->cantidad,
                    'cantidad_salida'   => 0,
                    'stock_antes'       => $stockAntes,
                    'stock_despues'     => $nuevoStock,
                    'precio_antes'      => (float) $articulo->precio_unitario,
                    'precio_despues'    => $nuevoPrecio,
                    'precio_movimiento' => $precioAnterior,
                    'subtotal'          => $det->subtotal ?? 0,
                    'iva_valor'         => $det->iva_valor ?? 0,
                    'total_linea'       => $det->total_linea ?? 0,
                    'valor_saldo'       => round($nuevoStock * $nuevoPrecio, 2),
                    'usuario'           => $request->user()->id_emp,
                    'observacion'       => $request->motivo_reverso,
                    'created_at'        => now(),
                ]);
            }

            $egreso->update([
                'estado'           => 'BORRADOR',
                'usuario_despacho' => null,
                'fecha_despacho'   => null,
                'motivo_reverso'   => $request->motivo_reverso,
                'usuario_reverso'  => $request->user()->id_emp,
                'fecha_reverso'    => now(),
            ]);
        });

        AuditoriaService::log('adq.egreso', $egreso->id, 'REVERSAR_EGRESO',
            ['estado' => 'DESPACHADO'],
            ['estado' => 'BORRADOR', 'motivo_reverso' => $request->motivo_reverso],
            $request, "Reverso de egreso de bodega #{$egreso->numero_secuencial}/{$egreso->anio}");

        return response()->json($egreso->load('detalles.articulo'));
    }

    public function pdf($id)
    {
        $egreso = Egreso::with(['detalles.articulo'])->findOrFail($id);
        if ($egreso->estado !== 'DESPACHADO') {
            return response()->json(['message' => 'El PDF solo está disponible para egresos confirmados.'], 422);
        }
        $pdf = Pdf::loadView('reportes.egreso_bodega', compact('egreso'))
            ->setPaper('a4', 'portrait');
        return $pdf->download("egreso-bodega-{$egreso->id}.pdf");
    }

    public function destroy($id)
    {
        $egreso = Egreso::findOrFail($id);
        if ($egreso->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede eliminar un egreso en BORRADOR.'], 422);
        }
        $egreso->delete();
        return response()->json(['message' => 'Egreso eliminado.']);
    }

    public function departamentos()
    {
        $deptos = DB::table('dbo.ad_departamento')
            ->where('id_depto', '!=', 999)
            ->where('estado', 'ACTIVO')
            ->orderBy('nombre_depto')
            ->get(['id_depto', 'nombre_depto']);
        return response()->json($deptos);
    }

    public function empleadosPorDepto(Request $request)
    {
        $deptoId = $request->get('depto');
        $query = DB::table('dbo.ad_empleado')
            ->where('estado', 'ACTIVO')
            ->where('id_depto', '!=', 999)
            ->orderBy('apellido_emp')
            ->orderBy('nombre_emp');

        if ($deptoId) {
            $query->where('id_depto', $deptoId);
        }

        $empleados = $query->get(['id_emp', 'nombre_emp', 'apellido_emp', 'id_depto']);

        return response()->json($empleados->map(fn($e) => [
            'id_emp'         => $e->id_emp,
            'nombre_completo' => trim($e->apellido_emp) . ' ' . trim($e->nombre_emp),
            'id_depto'       => $e->id_depto,
        ]));
    }

    private function calcularDetalles(array $detalles): array
    {
        $subtotalTotal = 0;
        $ivaTotal      = 0;
        $items         = [];

        foreach ($detalles as $det) {
            $articulo = Articulo::findOrFail($det['articulo_id']);
            $precio   = (float) $articulo->precio_unitario;
            $ivaId    = $articulo->iva_id;
            $ivaPct   = 0;

            if ($ivaId) {
                $iva    = Iva::find($ivaId);
                $ivaPct = $iva ? (float) $iva->porcentaje : 0;
            }

            $subCents   = (int) round((float) $det['cantidad'] * $precio * 100);
            $ivaCents   = (int) round($subCents * $ivaPct / 100);
            $subtotal   = $subCents / 100;
            $ivaValor   = $ivaCents / 100;
            $totalLinea = ($subCents + $ivaCents) / 100;

            $subtotalTotal += $subtotal;
            $ivaTotal      += $ivaValor;

            $items[] = [
                'articulo_id'     => $det['articulo_id'],
                'cantidad'        => $det['cantidad'],
                'precio_unitario' => $precio,
                'iva_id'          => $ivaId,
                'iva_porcentaje'  => $ivaPct,
                'subtotal'        => $subtotal,
                'iva_valor'       => $ivaValor,
                'total_linea'     => $totalLinea,
            ];
        }

        return [
            'detalles' => $items,
            'subtotal' => round($subtotalTotal, 5),
            'iva_valor' => round($ivaTotal, 5),
            'total'    => round($subtotalTotal + $ivaTotal, 5),
        ];
    }
}
