<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModalidadLaboral;
use Illuminate\Http\Request;

class ModalidadLaboralController extends Controller
{
    public function index()
    {
        return response()->json(ModalidadLaboral::orderBy('orden')->orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:pgsql.dbo.d2_modalidad_laboral,nombre',
        ]);

        $orden = ModalidadLaboral::max('orden') + 1;

        $m = ModalidadLaboral::create([
            'nombre' => trim($request->nombre),
            'estado' => 'ACTIVO',
            'orden'  => $orden,
        ]);

        return response()->json($m, 201);
    }

    public function update(Request $request, $id)
    {
        $m = ModalidadLaboral::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100|unique:pgsql.dbo.d2_modalidad_laboral,nombre,' . $id,
            'estado' => 'required|in:ACTIVO,INACTIVO',
        ]);

        $m->update([
            'nombre' => trim($request->nombre),
            'estado' => $request->estado,
        ]);

        return response()->json($m);
    }
}
