<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Articulo;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AjusteController extends Controller
{
    private const ROLES_ADQ = ['ADMINISTRADOR', 'ADQUISICIONES', 'BIENES'];

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $query = DB::table('adq.kardex as k')
            ->join('adq.articulo as a', 'a.id', '=', 'k.articulo_id')
            ->whereIn('k.tipo_movimiento', ['AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO'])
            ->select([
                'k.id', 'k.fecha', 'k.tipo_movimiento',
                'k.numero_documento',
                'k.cantidad_entrada', 'k.cantidad_salida',
                'k.stock_antes', 'k.stock_despues',
                'k.precio_despues',
                'k.observacion', 'k.usuario',
                'a.codigo as articulo_codigo',
                'a.nombre as articulo_nombre',
            ])
            ->orderByDesc('k.fecha')
            ->orderByDesc('k.id');

        if ($request->filled('articulo_id')) {
            $query->where('k.articulo_id', $request->articulo_id);
        }
        if ($request->filled('desde')) {
            $query->where('k.fecha', '>=', $request->desde . ' 00:00:00');
        }
        if ($request->filled('hasta')) {
            $query->where('k.fecha', '<=', $request->hasta . ' 23:59:59');
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $request->validate([
            'articulo_id'     => 'required|exists:pgsql.adq.articulo,id',
            'cantidad_fisica' => 'required|numeric|min:0',
            'motivo'          => 'required|string|min:5|max:500',
        ], [
            'cantidad_fisica.required' => 'Ingrese la cantidad física contada.',
            'cantidad_fisica.min'      => 'La cantidad física no puede ser negativa.',
            'motivo.required'          => 'El motivo del ajuste es obligatorio.',
            'motivo.min'               => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $articulo      = Articulo::findOrFail($request->articulo_id);
        $stockAntes    = (float) $articulo->stock_actual;
        $cantFisica    = (float) $request->cantidad_fisica;
        $diferencia    = round($cantFisica - $stockAntes, 5);

        if ($diferencia == 0) {
            return response()->json(['message' => 'El stock físico coincide con el sistema. No se requiere ajuste.'], 422);
        }

        $tipo          = $diferencia > 0 ? 'AJUSTE_POSITIVO' : 'AJUSTE_NEGATIVO';
        $cantEntrada   = $diferencia > 0 ? abs($diferencia) : 0;
        $cantSalida    = $diferencia < 0 ? abs($diferencia) : 0;
        $stockDespues  = $cantFisica;

        DB::transaction(function () use ($articulo, $stockAntes, $stockDespues, $cantEntrada, $cantSalida, $tipo, $request) {
            DB::table('adq.articulo')
                ->where('id', $articulo->id)
                ->update([
                    'stock_actual' => $stockDespues,
                    'updated_at'   => now(),
                ]);

            DB::table('adq.kardex')->insert([
                'articulo_id'       => $articulo->id,
                'fecha'             => now(),
                'tipo_movimiento'   => $tipo,
                'referencia_tipo'   => 'ajuste',
                'referencia_id'     => $articulo->id,
                'referencia_det_id' => 0,
                'numero_documento'  => $request->numero_documento ?? null,
                'cantidad_entrada'  => $cantEntrada,
                'cantidad_salida'   => $cantSalida,
                'stock_antes'       => $stockAntes,
                'stock_despues'     => $stockDespues,
                'precio_antes'      => (float) $articulo->precio_unitario,
                'precio_despues'    => (float) $articulo->precio_unitario,
                'precio_movimiento' => (float) $articulo->precio_unitario,
                'subtotal'          => 0,
                'iva_valor'         => 0,
                'total_linea'       => 0,
                'valor_saldo'       => round($stockDespues * (float) $articulo->precio_unitario, 2),
                'usuario'           => $request->user()->id_emp,
                'observacion'       => $request->motivo,
                'created_at'        => now(),
            ]);
        });

        $articulo->refresh();

        AuditoriaService::log('adq.articulo', $articulo->id, $tipo,
            ['stock_actual' => $stockAntes],
            ['stock_actual' => $stockDespues, 'motivo' => $request->motivo],
            $request, "Ajuste de inventario: {$articulo->nombre} ({$tipo})");

        return response()->json([
            'message'      => 'Ajuste registrado correctamente.',
            'tipo'         => $tipo,
            'stock_antes'  => $stockAntes,
            'stock_despues'=> $stockDespues,
            'diferencia'   => $stockDespues - $stockAntes,
            'articulo'     => $articulo,
        ]);
    }

    public function importarStock(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $request->validate(['archivo' => 'required|file|mimes:csv,txt']);

        $lineas = array_filter(
            array_map('str_getcsv', file($request->file('archivo')->getRealPath())),
            fn($r) => count(array_filter(array_map('trim', $r))) > 0
        );

        array_shift($lineas); // quitar encabezado

        $procesados    = 0;
        $noEncontrados = [];

        DB::transaction(function () use ($lineas, $request, &$procesados, &$noEncontrados) {
            foreach ($lineas as $fila) {
                $fila   = array_map('trim', $fila);
                $codigo = $fila[0] ?? null;
                if (!$codigo) continue;

                $stockNuevo = isset($fila[1]) && $fila[1] !== '' ? (float) $fila[1] : null;
                $precio     = isset($fila[2]) && $fila[2] !== '' ? (float) $fila[2] : null;
                $unidad     = isset($fila[3]) && $fila[3] !== '' ? $fila[3] : null;

                $articulo = DB::table('adq.articulo')->where('codigo', $codigo)->first();
                if (!$articulo) {
                    $noEncontrados[] = $codigo;
                    continue;
                }

                $precioFinal = $precio ?? (float) $articulo->precio_unitario;

                $updates = ['updated_at' => now()];
                if ($precio !== null)  $updates['precio_unitario'] = $precio;
                if ($unidad !== null)  $updates['unidad_medida']   = $unidad;

                if ($stockNuevo !== null) {
                    $stockAntes  = (float) $articulo->stock_actual;
                    $diferencia  = round($stockNuevo - $stockAntes, 5);
                    $updates['stock_actual'] = $stockNuevo;

                    if ($diferencia != 0) {
                        $tipo        = $diferencia > 0 ? 'AJUSTE_POSITIVO' : 'AJUSTE_NEGATIVO';
                        $cantEntrada = $diferencia > 0 ? abs($diferencia) : 0;
                        $cantSalida  = $diferencia < 0 ? abs($diferencia) : 0;

                        DB::table('adq.kardex')->insert([
                            'articulo_id'       => $articulo->id,
                            'fecha'             => now(),
                            'tipo_movimiento'   => $tipo,
                            'referencia_tipo'   => 'ajuste',
                            'referencia_id'     => $articulo->id,
                            'referencia_det_id' => 0,
                            'numero_documento'  => 'CARGA INICIAL',
                            'cantidad_entrada'  => $cantEntrada,
                            'cantidad_salida'   => $cantSalida,
                            'stock_antes'       => $stockAntes,
                            'stock_despues'     => $stockNuevo,
                            'precio_antes'      => (float) $articulo->precio_unitario,
                            'precio_despues'    => $precioFinal,
                            'precio_movimiento' => $precioFinal,
                            'subtotal'          => 0,
                            'iva_valor'         => 0,
                            'total_linea'       => 0,
                            'valor_saldo'       => round($stockNuevo * $precioFinal, 2),
                            'usuario'           => $request->user()->id_emp,
                            'observacion'       => 'Carga inicial de stock',
                            'created_at'        => now(),
                        ]);
                    }
                }

                DB::table('adq.articulo')->where('id', $articulo->id)->update($updates);
                $procesados++;
            }
        });

        return response()->json([
            'procesados'     => $procesados,
            'no_encontrados' => $noEncontrados,
        ]);
    }
}
