<?php
namespace App\Http\Controllers\Tecnologia;

use App\Http\Controllers\Controller;
use App\Models\Tecnologia\TipoEquipo;
use Illuminate\Http\Request;

class TipoEquipoController extends Controller
{
    private const ROLES_TEC = ['ADMINISTRADOR', 'TECNOLOGIA'];

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_TEC);
        return response()->json(TipoEquipo::orderBy('nombre')->get());
    }

    public function activos(Request $request)
    {
        $this->requireRole($request, self::ROLES_TEC);
        return response()->json(TipoEquipo::where('estado', true)->orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $request->validate([
            'nombre' => 'required|string|max:50|unique:pgsql.dbo.ti_tipo_equipo,nombre',
        ]);

        $tipo = TipoEquipo::create([
            'nombre' => strtoupper($request->nombre),
            'estado' => true,
        ]);

        return response()->json($tipo, 201);
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_TEC);
        $tipo = TipoEquipo::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:50|unique:pgsql.dbo.ti_tipo_equipo,nombre,' . $id,
            'estado' => 'nullable|boolean',
        ]);

        $tipo->update([
            'nombre' => strtoupper($request->nombre),
            'estado' => $request->has('estado') ? $request->boolean('estado') : $tipo->estado,
        ]);

        return response()->json($tipo);
    }
}
