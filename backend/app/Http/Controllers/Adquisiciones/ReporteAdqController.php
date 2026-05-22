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
            'articulo_id' => 'nullable|exists:pgsql.adq.articulo,id',
            'nivel1'      => 'nullable|string|max:2',
            'nivel2'      => 'nullable|string|max:6',
            'desde'       => 'required|date',
            'hasta'       => 'required|date|after_or_equal:desde',
        ]);

        if (!$request->articulo_id && !$request->nivel1 && !$request->nivel2) {
            return response()->json(['message' => 'Seleccione un artículo o un nivel MEF.'], 422);
        }

        $queryArticulos = DB::table('adq.articulo')->where('estado', 'ACTIVO');
        if ($request->articulo_id) $queryArticulos->where('id', $request->articulo_id);
        if ($request->nivel1)      $queryArticulos->where('nivel1', $request->nivel1);
        if ($request->nivel2)      $queryArticulos->where('nivel2', $request->nivel2);
        $articulos = $queryArticulos->orderBy('nombre')->get();

        $resultados = $articulos->map(function ($articulo) use ($request) {
            $filas = DB::table('adq.kardex')
                ->where('articulo_id', $articulo->id)
                ->whereBetween('fecha', [$request->desde . ' 00:00:00', $request->hasta . ' 23:59:59'])
                ->orderBy('fecha')
                ->orderBy('id')
                ->get();
            return ['articulo' => $articulo, 'filas' => $filas];
        })->filter(fn($r) => $r['filas']->isNotEmpty())->values();

        if ($request->formato === 'pdf') {
            $meses    = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $filename = $request->nivel2 ? "kardex-{$request->nivel2}.pdf"
                      : ($request->nivel1  ? "kardex-nivel-{$request->nivel1}.pdf"
                      : "kardex-{$articulos->first()->codigo}.pdf");
            $pdf = Pdf::loadView('reportes.kardex', compact('resultados', 'meses', 'request'))
                ->setPaper('a4', 'landscape');
            return $pdf->download($filename);
        }

        return response()->json($resultados);
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
                'oc.descuento',
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
        if ($request->filled('proveedor')) {
            $q = $request->proveedor;
            $query->where(function ($q2) use ($q) {
                $q2->where('p.ruc', 'ilike', "%{$q}%")
                   ->orWhere('p.nombre', 'ilike', "%{$q}%");
            });
        }

        $filas = $query->get();

        if ($request->formato === 'pdf') {
            $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
            $pdf = Pdf::loadView('reportes.libro_compras', compact('filas', 'meses', 'request'))
                ->setPaper('a4', 'landscape');
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
                ->setPaper('a4', 'landscape');
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

    public function analitica(Request $request)
    {
        $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $desde = $request->desde;
        $hasta = $request->hasta;

        // Top proveedores por monto (órdenes confirmadas)
        $topProveedores = DB::table('adq.orden_compra as o')
            ->join('adq.proveedor as p', 'p.id', '=', 'o.proveedor_id')
            ->where('o.estado', 'RECIBIDO')
            ->whereBetween('o.fecha_documento', [$desde, $hasta])
            ->select('p.ruc', 'p.nombre',
                DB::raw('COUNT(o.id) as facturas'),
                DB::raw('SUM(o.total) as total'))
            ->groupBy('p.id', 'p.ruc', 'p.nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Top áreas que más solicitan materiales
        $topAreas = DB::table('adq.solicitud_material as s')
            ->join('dbo.ad_departamento as d', 'd.id_depto', '=', 's.id_depto')
            ->join('adq.solicitud_material_det as sd', 'sd.solicitud_id', '=', 's.id')
            ->whereIn('s.estado', ['APROBADO', 'DESPACHADO', 'DESPACHADO PARCIAL'])
            ->whereBetween('s.fecha', [$desde, $hasta])
            ->where('d.id_depto', '!=', 999)
            ->select('d.nombre_depto',
                DB::raw('COUNT(DISTINCT s.id) as total_solicitudes'),
                DB::raw('SUM(sd.cantidad_solicitada) as total_articulos'))
            ->groupBy('d.id_depto', 'd.nombre_depto')
            ->orderByDesc('total_solicitudes')
            ->limit(10)
            ->get();

        // Top artículos más solicitados
        $topArticulos = DB::table('adq.solicitud_material_det as sd')
            ->join('adq.solicitud_material as s', 's.id', '=', 'sd.solicitud_id')
            ->join('adq.articulo as a', 'a.id', '=', 'sd.articulo_id')
            ->whereIn('s.estado', ['APROBADO', 'DESPACHADO', 'DESPACHADO PARCIAL'])
            ->whereBetween('s.fecha', [$desde, $hasta])
            ->select('a.nombre', 'a.codigo',
                DB::raw('SUM(sd.cantidad_solicitada) as total_solicitado'),
                DB::raw('COUNT(DISTINCT s.id) as total_solicitudes'))
            ->groupBy('a.id', 'a.nombre', 'a.codigo')
            ->orderByDesc('total_solicitado')
            ->limit(10)
            ->get();

        return response()->json([
            'top_proveedores' => $topProveedores,
            'top_areas'       => $topAreas,
            'top_articulos'   => $topArticulos,
        ]);
    }
}
