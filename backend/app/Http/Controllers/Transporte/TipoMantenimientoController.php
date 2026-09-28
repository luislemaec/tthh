<?php
namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\TipoMantenimiento;
use App\Services\AuditoriaService;
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

        AuditoriaService::log('dbo.trans_tipo_mantenimiento', $tipo->id, 'CREAR',
            null, ['nombre' => $tipo->nombre], $request, 'Tipo de mantenimiento creado: ' . $tipo->nombre);

        return response()->json($tipo, 201);
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_TRANSPORTE);
        $tipo = TipoMantenimiento::findOrFail($id);
        $anterior = $tipo->only(['nombre', 'estado']);

        $request->validate([
            'nombre' => 'required|in:PREVENTIVO,CORRECTIVO,PREVENTIVO Y CORRECTIVO',
            'estado' => 'nullable|in:ACTIVO,INACTIVO',
        ]);

        $tipo->update([
            'nombre' => $request->nombre,
            'estado' => $request->estado ?? $tipo->estado,
        ]);

        AuditoriaService::log('dbo.trans_tipo_mantenimiento', $tipo->id, 'ACTUALIZAR',
            $anterior, $tipo->only(['nombre', 'estado']), $request, 'Tipo de mantenimiento actualizado: ' . $tipo->nombre);

        return response()->json($tipo);
    }
}
