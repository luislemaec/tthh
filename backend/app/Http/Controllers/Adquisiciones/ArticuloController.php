<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Articulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticuloController extends Controller
{
    public function index()
    {
        $porcentaje = (float)(DB::table('adq.configuracion')
            ->where('concepto', 'porcentaje_stock_minimo')
            ->value('valor') ?? 20);

        $articulos = Articulo::orderBy('nombre')->get()->map(function ($a) use ($porcentaje) {
            $a->bajo_minimo = $a->stock_maximo_historico > 0 &&
                $a->stock_actual <= ($a->stock_maximo_historico * $porcentaje / 100);
            return $a;
        });

        return response()->json($articulos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo'       => 'required|string|max:30|unique:pgsql.adq.articulo,codigo',
            'nombre'       => 'required|string|max:200',
            'unidad_medida' => 'nullable|string|max:50',
            'categoria'    => 'nullable|string|max:100',
        ]);

        $articulo = Articulo::create($request->only([
            'codigo', 'nombre', 'descripcion', 'unidad_medida', 'categoria',
        ]));

        return response()->json($articulo, 201);
    }

    public function show($id)
    {
        return response()->json(Articulo::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $articulo = Articulo::findOrFail($id);

        $request->validate([
            'codigo' => 'required|string|max:30|unique:pgsql.adq.articulo,codigo,' . $id,
            'nombre' => 'required|string|max:200',
        ]);

        $articulo->update($request->only([
            'codigo', 'nombre', 'descripcion', 'unidad_medida', 'categoria',
        ]));

        return response()->json($articulo);
    }

    public function inactivar($id)
    {
        Articulo::findOrFail($id)->update(['estado' => 'INACTIVO']);
        return response()->json(['message' => 'Artículo inactivado.']);
    }

    public function alertas()
    {
        $porcentaje = (float)(DB::table('adq.configuracion')
            ->where('concepto', 'porcentaje_stock_minimo')
            ->value('valor') ?? 20);

        $alertas = Articulo::where('estado', 'ACTIVO')
            ->get()
            ->filter(fn($a) =>
                $a->stock_maximo_historico > 0 &&
                $a->stock_actual <= ($a->stock_maximo_historico * $porcentaje / 100)
            )
            ->values();

        return response()->json($alertas);
    }

    public function configuracion()
    {
        $config = DB::table('adq.configuracion')->get()->keyBy('concepto');
        return response()->json($config);
    }

    public function actualizarConfiguracion(Request $request)
    {
        $request->validate([
            'porcentaje_stock_minimo' => 'required|numeric|min:1|max:100',
        ]);

        DB::table('adq.configuracion')
            ->where('concepto', 'porcentaje_stock_minimo')
            ->update(['valor' => $request->porcentaje_stock_minimo, 'updated_at' => now()]);

        return response()->json(['message' => 'Configuración actualizada.']);
    }
}
