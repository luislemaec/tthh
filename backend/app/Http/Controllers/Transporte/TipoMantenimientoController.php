<?php
namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\TipoMantenimiento;
use Illuminate\Http\Request;

class TipoMantenimientoController extends Controller
{
    private const ROLES_LECTURA = ['ADMINISTRADOR', 'TRANSPORTE', 'CONDUCTOR'];
    private const ROLES_TRANSPORTE = ['ADMINISTRADOR', 'TRANSPORTE'];

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_LECTURA);
        return response()->json(TipoMantenimiento::orderBy('nombre')->get());
    }

    public function activos(Request $request)
    {
        $this->requireRole($request, self::ROLES_LECTURA);
        return response()->json(TipoMantenimiento::where('estado', 'ACTIVO')->orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_TRANSPORTE);
        $request->validate([
            'nombre' => 'required|in:PREVENTIVO,CORRECTIVO,PREVENTIVO Y CORRECTIVO',
        ]);

        $tipo = TipoMantenimiento::create([
            'nombre' => $request->nombre,
            'estado' => 'ACTIVO',
        ]);

        return response()->json($tipo, 201);
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_TRANSPORTE);
        $tipo = TipoMantenimiento::findOrFail($id);

        $request->validate([
            'nombre' => 'required|in:PREVENTIVO,CORRECTIVO,PREVENTIVO Y CORRECTIVO',
            'estado' => 'nullable|in:ACTIVO,INACTIVO',
        ]);

        $tipo->update([
            'nombre' => $request->nombre,
            'estado' => $request->estado ?? $tipo->estado,
        ]);

        return response()->json($tipo);
    }
}
