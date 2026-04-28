<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteAdqController extends Controller
{
    public function kardex(Request $request)
    {
        $request->validate([
            'articulo_id' => 'required|exists:pgsql.adq.articulo,id',
            'desde'       => 'required|date',
            'hasta'       => 'required|date|after_or_equal:desde',
        ]);

        $articulo = DB::table('adq.articulo')->where('id', $request->articulo_id)->first();

        $filas = DB::table('adq.kardex')
            ->where('articulo_id', $request->articulo_id)
            ->whereBetween('fecha', [$request->desde . ' 00:00:00', $request->hasta . ' 23:59:59'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        if ($request->formato === 'pdf') {
            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $pdf = Pdf::loadView('reportes.kardex', compact('articulo', 'filas', 'meses', 'request'))
                ->setPaper('legal', 'landscape');
            return $pdf->download("kardex-{$articulo->codigo}.pdf");
        }

        return response()->json(['articulo' => $articulo, 'filas' => $filas]);
    }

    public function libroCompras(Request $request)
    {
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $query = DB::table('adq.orden_compra as oc')
            ->join('adq.proveedor as p', 'p.id', '=', 'oc.proveedor_id')
            ->where('oc.estado', 'RECIBIDO')
            ->where('oc.tipo_documento', 'FACTURA')
            ->whereBetween('oc.fecha_documento', [$request->desde, $request->hasta])
            ->select([
                'oc.id',
                'oc.fecha_documento',
                'oc.numero_documento',
                'oc.proceso_contratacion',
                'oc.subtotal',
                'oc.iva_valor',
                'oc.total',
                'p.ruc',
                'p.nombre as proveedor_nombre',
            ])
            ->orderBy('oc.fecha_documento')
            ->orderBy('oc.id');

        if ($request->proceso) {
            $query->where('oc.proceso_contratacion', $request->proceso);
        }

        $filas = $query->get();

        if ($request->formato === 'pdf') {
            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $pdf = Pdf::loadView('reportes.libro_compras', compact('filas', 'meses', 'request'))
                ->setPaper('legal', 'landscape');
            return $pdf->download("libro-compras.pdf");
        }

        return response()->json($filas);
    }

    public function egresosValorizados(Request $request)
    {
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $query = DB::table('adq.egreso as e')
            ->join('adq.egreso_det as ed', 'ed.egreso_id', '=', 'e.id')
            ->join('adq.articulo as a', 'a.id', '=', 'ed.articulo_id')
            ->where('e.estado', 'DESPACHADO')
            ->whereBetween('e.fecha_despacho', [$request->desde . ' 00:00:00', $request->hasta . ' 23:59:59'])
            ->select([
                'e.numero_secuencial', 'e.anio',
                'e.fecha_despacho', 'e.direccion',
                'e.empleado_nombre',
                'a.codigo as articulo_codigo',
                'a.nombre as articulo_nombre',
                'ed.cantidad',
                'ed.precio_unitario',
                'ed.subtotal',
                'ed.iva_valor',
                'ed.total_linea',
            ])
            ->orderBy('e.fecha_despacho')
            ->orderBy('e.id')
            ->orderBy('ed.id');

        if ($request->filled('direccion')) {
            $query->where('e.direccion', $request->direccion);
        }

        $filas = $query->get();

        if ($request->formato === 'pdf') {
            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $pdf = Pdf::loadView('reportes.egresos_valorizados', compact('filas', 'meses', 'request'))
                ->setPaper('legal', 'landscape');
            return $pdf->download("egresos-valorizados.pdf");
        }

        return response()->json($filas);
    }

    public function articulosBuscar(Request $request)
    {
        $q = $request->get('q', '');
        $articulos = DB::table('adq.articulo')
            ->where('estado', 'ACTIVO')
            ->where(function ($query) use ($q) {
                $query->where('codigo', 'ilike', "%{$q}%")
                      ->orWhere('nombre', 'ilike', "%{$q}%");
            })
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'codigo', 'nombre']);

        return response()->json($articulos);
    }
}
