<?php
namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Adq\Proveedor;
use Illuminate\Http\Request;

class TallerController extends Controller
{
    public function index()
    {
        return response()->json(Proveedor::where('es_taller', true)->orderBy('nombre')->get());
    }

    public function activos()
    {
        return response()->json(
            Proveedor::where('es_taller', true)->where('estado', 'ACTIVO')->orderBy('nombre')->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'              => 'required|string|max:100',
            'ruc'                 => 'nullable|string|max:20|unique:pgsql.adq.proveedor,ruc',
            'direccion'           => 'nullable|string|max:200',
            'email'               => 'nullable|email|max:100',
            'telefono'            => 'nullable|string|max:20',
            'orden_compra'        => 'nullable|string|max:50',
            'es_proveedor_bienes' => 'boolean',
        ]);

        $proveedor = Proveedor::create([
            'ruc'                 => $request->ruc ?? null,
            'nombre'              => $request->nombre,
            'direccion'           => $request->direccion ?? null,
            'email'               => $request->email ?? null,
            'telefono'            => $request->telefono ?? null,
            'orden_compra'        => $request->orden_compra ?? null,
            'estado'              => 'ACTIVO',
            'es_taller'           => true,
            'es_proveedor_bienes' => $request->boolean('es_proveedor_bienes', false),
        ]);

        return response()->json($proveedor, 201);
    }

    public function update(Request $request, $id)
    {
        $proveedor = Proveedor::where('es_taller', true)->findOrFail($id);

        $request->validate([
            'nombre'              => 'required|string|max:100',
            'ruc'                 => 'nullable|string|max:20|unique:pgsql.adq.proveedor,ruc,' . $id,
            'direccion'           => 'nullable|string|max:200',
            'email'               => 'nullable|email|max:100',
            'telefono'            => 'nullable|string|max:20',
            'orden_compra'        => 'nullable|string|max:50',
            'estado'              => 'nullable|in:ACTIVO,INACTIVO',
            'es_proveedor_bienes' => 'boolean',
        ]);

        $proveedor->update([
            'nombre'              => $request->nombre,
            'ruc'                 => $request->ruc ?? $proveedor->ruc,
            'direccion'           => $request->direccion,
            'email'               => $request->email,
            'telefono'            => $request->telefono,
            'orden_compra'        => $request->orden_compra,
            'estado'              => $request->estado ?? $proveedor->estado,
            'es_proveedor_bienes' => $request->boolean('es_proveedor_bienes', $proveedor->es_proveedor_bienes),
        ]);

        return response()->json($proveedor);
    }
}
