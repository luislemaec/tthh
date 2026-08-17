<?php
namespace App\Http\Controllers\Adquisiciones;

use App\Http\Controllers\Controller;
use App\Models\Adq\Iva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IvaController extends Controller
{
    private const ROLES_ADQ = ['ADMINISTRADOR', 'ADQUISICIONES', 'BIENES'];

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        return response()->json(Iva::orderBy('porcentaje')->get());
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADQ);
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
        $this->requireRole($request, self::ROLES_ADQ);
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

    public function toggle(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADQ);
        $iva = Iva::findOrFail($id);
        $iva->update(['activo' => !$iva->activo]);
        return response()->json($iva);
    }

}
