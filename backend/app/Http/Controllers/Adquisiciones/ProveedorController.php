<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Proveedor;
use App\Models\Adq\ProveedorCatalogo;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    private const ROLES_ADQ = ['ADMINISTRADOR', 'ADQUISICIONES', 'BIENES'];

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        return response()->json(
            Proveedor::with('catalogo')->orderBy('nombre')->get()
        );
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $request->validate([
            'ruc'      => 'required|string|max:20|unique:pgsql.adq.proveedor,ruc',
            'nombre'   => 'required|string|max:200',
            'email'    => 'nullable|email|max:100',
            'catalogo' => 'nullable|array',
            'catalogo.*.descripcion'     => 'required|string|max:300',
            'catalogo.*.unidad_medida'   => 'nullable|string|max:50',
            'catalogo.*.precio_referencial' => 'nullable|numeric|min:0',
        ]);

        $proveedor = Proveedor::create(array_merge(
            $request->only(['ruc', 'nombre', 'direccion', 'contacto', 'email', 'telefono']),
            [
                'es_proveedor_bienes' => true,
                'es_taller'           => $request->boolean('es_taller', false),
            ]
        ));

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

    public function show(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        return response()->json(Proveedor::with('catalogo')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADQ);
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

        $proveedor->update(array_merge(
            $request->only(['ruc', 'nombre', 'direccion', 'contacto', 'email', 'telefono']),
            ['es_taller' => $request->boolean('es_taller', $proveedor->es_taller)]
        ));

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

    public function inactivar(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $proveedor = Proveedor::findOrFail($id);
        $proveedor->update(['estado' => 'INACTIVO']);
        return response()->json(['message' => 'Proveedor inactivado.']);
    }

    public function activar(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $proveedor = Proveedor::findOrFail($id);
        $proveedor->update(['estado' => 'ACTIVO']);
        return response()->json(['message' => 'Proveedor activado.']);
    }
}
