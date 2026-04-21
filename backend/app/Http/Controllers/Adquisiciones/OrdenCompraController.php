<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Articulo;
use App\Models\Adq\OrdenCompra;
use App\Models\Adq\OrdenCompraDet;
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
        $request->validate([
            'proveedor_id' => 'required|exists:pgsql.adq.proveedor,id',
            'fecha'        => 'required|date',
            'detalles'     => 'required|array|min:1',
            'detalles.*.articulo_id'    => 'required|exists:pgsql.adq.articulo,id',
            'detalles.*.cantidad'       => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'nullable|numeric|min:0',
        ]);

        $orden = OrdenCompra::create([
            'proveedor_id'     => $request->proveedor_id,
            'fecha'            => $request->fecha,
            'estado'           => 'BORRADOR',
            'observacion'      => $request->observacion,
            'usuario_registro' => $request->user()->id_emp,
        ]);

        foreach ($request->detalles as $det) {
            OrdenCompraDet::create([
                'orden_id'       => $orden->id,
                'articulo_id'    => $det['articulo_id'],
                'cantidad'       => $det['cantidad'],
                'precio_unitario' => $det['precio_unitario'] ?? 0,
            ]);
        }

        return response()->json($orden->load(['proveedor', 'detalles.articulo']), 201);
    }

    public function show($id)
    {
        return response()->json(OrdenCompra::with(['proveedor', 'detalles.articulo'])->findOrFail($id));
    }

    public function enviar($id)
    {
        $orden = OrdenCompra::findOrFail($id);
        if ($orden->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede enviar una orden en BORRADOR.'], 422);
        }
        $orden->update(['estado' => 'ENVIADA']);
        return response()->json($orden);
    }

    public function recibir(Request $request, $id)
    {
        $orden = OrdenCompra::with('detalles')->findOrFail($id);
        if ($orden->estado !== 'ENVIADA') {
            return response()->json(['message' => 'Solo se pueden recibir órdenes en estado ENVIADA.'], 422);
        }

        DB::transaction(function () use ($orden, $request) {
            foreach ($orden->detalles as $det) {
                $articulo = Articulo::findOrFail($det->articulo_id);
                $nuevoStock = $articulo->stock_actual + $det->cantidad;
                $articulo->update([
                    'stock_actual'            => $nuevoStock,
                    'stock_maximo_historico'  => max($articulo->stock_maximo_historico, $nuevoStock),
                ]);
            }

            $orden->update([
                'estado'           => 'RECIBIDA',
                'usuario_recepcion' => $request->user()->id_emp,
                'fecha_recepcion'   => now(),
            ]);
        });

        return response()->json($orden->load(['proveedor', 'detalles.articulo']));
    }

    public function destroy($id)
    {
        $orden = OrdenCompra::findOrFail($id);
        if ($orden->estado !== 'BORRADOR') {
            return response()->json(['message' => 'Solo se puede eliminar una orden en BORRADOR.'], 422);
        }
        $orden->delete();
        return response()->json(['message' => 'Orden eliminada.']);
    }
}
