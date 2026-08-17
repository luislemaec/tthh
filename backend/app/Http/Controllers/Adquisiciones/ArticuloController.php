<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Articulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArticuloController extends Controller
{
    private const ROLES_ADQ = ['ADMINISTRADOR', 'ADQUISICIONES', 'BIENES'];

    public function index(Request $request)
    {
        $porcentaje = (float)(DB::table('adq.configuracion')
            ->where('concepto', 'porcentaje_stock_minimo')
            ->value('valor') ?? 20);

        $query = Articulo::with('iva')->orderBy('nombre');
        if ($request->nivel1) $query->where('nivel1', $request->nivel1);
        if ($request->nivel2) $query->where('nivel2', $request->nivel2);
        if ($request->q)      $query->where(fn($q) => $q->where('nombre', 'ilike', "%{$request->q}%")
                                                         ->orWhere('codigo', 'ilike', "%{$request->q}%"));

        $articulos = $query->get()->map(function ($a) use ($porcentaje) {
            $a->bajo_minimo  = $a->stock_maximo_historico > 0 &&
                $a->stock_actual <= ($a->stock_maximo_historico * $porcentaje / 100);
            $ivaPct          = $a->iva ? (float)$a->iva->porcentaje : 0;
            $a->iva_porcentaje = $ivaPct;
            $a->iva_valor    = round((float)$a->precio_unitario * $ivaPct / 100, 4);
            $a->precio_total = round((float)$a->precio_unitario + $a->iva_valor, 4);
            $a->imagen_url   = $a->imagen ? Storage::disk('public')->url($a->imagen) : null;
            return $a;
        });

        return response()->json($articulos);
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $request->validate([
            'codigo'        => 'required|string|max:30|unique:pgsql.adq.articulo,codigo',
            'nombre'        => 'required|string|max:200',
            'unidad_medida' => 'nullable|string|max:60',
            'iva_id'        => 'nullable|exists:pgsql.adq.iva,id',
            'estado_fisico' => 'nullable|in:BUENO,MALO,INSERVIBLE',
        ]);

        // Auto-rellenar item_presupuestario desde el catálogo si viene nivel2
        $itemPresupuestario = $request->item_presupuestario;
        if ($request->nivel2 && !$itemPresupuestario) {
            $itemPresupuestario = DB::table('adq.catalogo_inventario')
                ->where('nivel2', $request->nivel2)
                ->value('asociacion_presupuestaria');
        }

        $articulo = Articulo::create(array_merge(
            $request->only(['codigo', 'nombre', 'descripcion', 'unidad_medida', 'categoria', 'marca', 'nivel1', 'nivel2', 'precio_unitario', 'iva_id', 'estado_fisico']),
            ['item_presupuestario' => $itemPresupuestario]
        ));

        return response()->json($articulo->load('iva'), 201);
    }

    public function show($id)
    {
        return response()->json(Articulo::with('iva')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $articulo = Articulo::findOrFail($id);

        $request->validate([
            'codigo'        => 'required|string|max:30|unique:pgsql.adq.articulo,codigo,' . $id,
            'nombre'        => 'required|string|max:200',
            'unidad_medida' => 'nullable|string|max:60',
            'iva_id'        => 'nullable|exists:pgsql.adq.iva,id',
            'estado_fisico' => 'nullable|in:BUENO,MALO,INSERVIBLE',
        ]);

        $itemPresupuestario = $request->item_presupuestario;
        if ($request->nivel2 && !$itemPresupuestario) {
            $itemPresupuestario = DB::table('adq.catalogo_inventario')
                ->where('nivel2', $request->nivel2)
                ->value('asociacion_presupuestaria');
        }

        $articulo->update(array_merge(
            $request->only(['codigo', 'nombre', 'descripcion', 'unidad_medida', 'categoria', 'marca', 'nivel1', 'nivel2', 'precio_unitario', 'iva_id', 'estado_fisico']),
            ['item_presupuestario' => $itemPresupuestario ?? $articulo->item_presupuestario]
        ));

        return response()->json($articulo->load('iva'));
    }

    public function subirImagen(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $request->validate(['imagen' => 'required|image|max:2048']);
        $articulo = Articulo::findOrFail($id);

        if ($articulo->imagen) {
            Storage::disk('public')->delete($articulo->imagen);
        }

        $path = $request->file('imagen')->store('articulos', 'public');
        $articulo->update(['imagen' => $path]);

        return response()->json(['imagen_url' => Storage::disk('public')->url($path)]);
    }

    public function inactivar(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        Articulo::findOrFail($id)->update(['estado' => 'INACTIVO']);
        return response()->json(['message' => 'Artículo inactivado.']);
    }

    public function alertas(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
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

    public function buscarCatalogo(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $q = $request->get('q', '');
        $items = DB::table('adq.catalogo_inventario')
            ->where(function ($query) use ($q) {
                $query->where('nivel2', 'ilike', "%$q%")
                      ->orWhere('descripcion', 'ilike', "%$q%");
            })
            ->orderBy('descripcion')
            ->limit(20)
            ->get(['nivel1', 'nivel2', 'descripcion', 'asociacion_presupuestaria']);
        return response()->json($items);
    }

    public function configuracion(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $config = DB::table('adq.configuracion')->get()->keyBy('concepto');
        return response()->json($config);
    }

    public function actualizarConfiguracion(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $request->validate([
            'porcentaje_stock_minimo' => 'required|numeric|min:1|max:100',
        ]);

        DB::table('adq.configuracion')
            ->where('concepto', 'porcentaje_stock_minimo')
            ->update(['valor' => $request->porcentaje_stock_minimo, 'updated_at' => now()]);

        return response()->json(['message' => 'Configuración actualizada.']);
    }
}
