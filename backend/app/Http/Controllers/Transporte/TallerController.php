<?php
namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\Taller;
use Illuminate\Http\Request;

class TallerController extends Controller
{
    public function index()
    {
        return response()->json(Taller::orderBy('nombre')->get());
    }

    public function activos()
    {
        return response()->json(Taller::where('estado', 'ACTIVO')->orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'       => 'required|string|max:100',
            'ruc'          => 'nullable|string|max:13',
            'direccion'    => 'nullable|string|max:200',
            'correo'       => 'nullable|email|max:100',
            'telefono'     => 'nullable|string|max:20',
            'orden_compra' => 'nullable|string|max:50',
        ]);

        $taller = Taller::create(array_merge(
            $request->only(['nombre', 'ruc', 'direccion', 'correo', 'telefono', 'orden_compra']),
            ['estado' => 'ACTIVO']
        ));

        return response()->json($taller, 201);
    }

    public function update(Request $request, $id)
    {
        $taller = Taller::findOrFail($id);

        $request->validate([
            'nombre'       => 'required|string|max:100',
            'ruc'          => 'nullable|string|max:13',
            'direccion'    => 'nullable|string|max:200',
            'correo'       => 'nullable|email|max:100',
            'telefono'     => 'nullable|string|max:20',
            'orden_compra' => 'nullable|string|max:50',
            'estado'       => 'nullable|in:ACTIVO,INACTIVO',
        ]);

        $taller->update($request->only(['nombre', 'ruc', 'direccion', 'correo', 'telefono', 'orden_compra', 'estado']));

        return response()->json($taller);
    }
}
