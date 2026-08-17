<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Articulo;
use App\Models\Adq\OrdenCompra;
use App\Models\Adq\SolicitudMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdqDashboardController extends Controller
{
    private const ROLES_ADQ = ['ADMINISTRADOR', 'ADQUISICIONES', 'BIENES'];

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $porcentaje = (float)(DB::table('adq.configuracion')
            ->where('concepto', 'porcentaje_stock_minimo')
            ->value('valor') ?? 20);

        $articulos = Articulo::where('estado', 'ACTIVO')->get();
        $bajoMinimo = $articulos->filter(fn($a) =>
            $a->stock_maximo_historico > 0 &&
            $a->stock_actual <= ($a->stock_maximo_historico * $porcentaje / 100)
        );

        return response()->json([
            'articulos_bajo_minimo'    => $bajoMinimo->count(),
            'alertas'                  => $bajoMinimo->values(),
            'solicitudes_pendientes'   => SolicitudMaterial::where('estado', 'PENDIENTE')->count(),
            'solicitudes_por_aprobar'  => SolicitudMaterial::where('estado', 'APROBADO')->count(),
            'ordenes_en_transito'      => OrdenCompra::where('estado', 'ENVIADA')->count(),
            'ordenes_recientes'        => OrdenCompra::with('proveedor')
                ->orderByDesc('created_at')->limit(5)->get(),
        ]);
    }
}
