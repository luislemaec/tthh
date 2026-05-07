<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Iva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IvaController extends Controller
{
    public function index()
    {
        return response()->json(Iva::orderBy('porcentaje')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'descripcion'    => 'required|string|max:50',
            'porcentaje'     => 'required|numeric|min:0|max:100',
            'fecha_vigencia' => 'nullable|date',
        ]);
        $iva = Iva::create([
            'descripcion'    => $request->descripcion,
            'porcentaje'     => $request->porcentaje,
            'fecha_vigencia' => $request->fecha_vigencia,
            'activo'         => true,
        ]);
        return response()->json($iva, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'descripcion'    => 'required|string|max:50',
            'porcentaje'     => 'required|numeric|min:0|max:100',
            'fecha_vigencia' => 'nullable|date',
        ]);
        $iva = Iva::findOrFail($id);
        $iva->update([
            'descripcion'    => $request->descripcion,
            'porcentaje'     => $request->porcentaje,
            'fecha_vigencia' => $request->fecha_vigencia,
        ]);
        return response()->json($iva);
    }

    public function toggle($id)
    {
        $iva = Iva::findOrFail($id);
        $iva->update(['activo' => !$iva->activo]);
        return response()->json($iva);
    }

}
