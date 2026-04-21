<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Proveedor;
use App\Models\Adq\ProveedorCatalogo;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index()
    {
        return response()->json(Proveedor::with('catalogo')->orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'ruc'      => 'required|string|max:20|unique:pgsql.adq.proveedor,ruc',
            'nombre'   => 'required|string|max:200',
            'email'    => 'nullable|email|max:100',
            'catalogo' => 'nullable|array',
            'catalogo.*.descripcion'     => 'required|string|max:300',
            'catalogo.*.unidad_medida'   => 'nullable|string|max:50',
            'catalogo.*.precio_referencial' => 'nullable|numeric|min:0',
        ]);

        $proveedor = Proveedor::create($request->only([
            'ruc', 'nombre', 'direccion', 'contacto', 'email', 'telefono',
        ]));

        foreach ($request->catalogo ?? [] as $item) {
            ProveedorCatalogo::create([
                'proveedor_id'      => $proveedor->id,
                'descripcion'       => $item['descripcion'],
                'unidad_medida'     => $item['unidad_medida'] ?? null,
                'precio_referencial' => $item['precio_referencial'] ?? 0,
            ]);
        }

        return response()->json($proveedor->load('catalogo'), 201);
    }

    public function show($id)
    {
        return response()->json(Proveedor::with('catalogo')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $proveedor = Proveedor::findOrFail($id);

        $request->validate([
            'ruc'    => 'required|string|max:20|unique:pgsql.adq.proveedor,ruc,' . $id,
            'nombre' => 'required|string|max:200',
            'email'  => 'nullable|email|max:100',
            'catalogo' => 'nullable|array',
            'catalogo.*.descripcion'        => 'required|string|max:300',
            'catalogo.*.unidad_medida'      => 'nullable|string|max:50',
            'catalogo.*.precio_referencial' => 'nullable|numeric|min:0',
        ]);

        $proveedor->update($request->only([
            'ruc', 'nombre', 'direccion', 'contacto', 'email', 'telefono',
        ]));

        ProveedorCatalogo::where('proveedor_id', $id)->delete();
        foreach ($request->catalogo ?? [] as $item) {
            ProveedorCatalogo::create([
                'proveedor_id'       => $id,
                'descripcion'        => $item['descripcion'],
                'unidad_medida'      => $item['unidad_medida'] ?? null,
                'precio_referencial' => $item['precio_referencial'] ?? 0,
            ]);
        }

        return response()->json($proveedor->load('catalogo'));
    }

    public function inactivar($id)
    {
        $proveedor = Proveedor::findOrFail($id);
        $proveedor->update(['estado' => 'INACTIVO']);
        return response()->json(['message' => 'Proveedor inactivado.']);
    }

    public function activar($id)
    {
        $proveedor = Proveedor::findOrFail($id);
        $proveedor->update(['estado' => 'ACTIVO']);
        return response()->json(['message' => 'Proveedor activado.']);
    }
}
