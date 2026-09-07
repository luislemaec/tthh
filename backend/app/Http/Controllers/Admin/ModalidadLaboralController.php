<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModalidadLaboral;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModalidadLaboralController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];

    public function index()
    {
        return response()->json(ModalidadLaboral::orderBy('orden')->orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            'nombre' => 'required|string|max:100|unique:pgsql.dbo.d2_modalidad_laboral,nombre',
        ]);

        $orden = ModalidadLaboral::max('orden') + 1;
        $user  = Auth::user()->id_emp ?? Auth::id();
        $now   = now();

        $m = ModalidadLaboral::create([
            'nombre'     => trim($request->nombre),
            'estado'     => 'ACTIVO',
            'orden'      => $orden,
            'created_at' => $now,
            'created_by' => $user,
            'updated_at' => $now,
            'updated_by' => $user,
        ]);

        AuditoriaService::log('dbo.d2_modalidad_laboral', $m->id, 'CREAR', null,
            ['nombre' => $m->nombre], $request, "Creación modalidad laboral {$m->nombre}");

        return response()->json($m, 201);
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $m = ModalidadLaboral::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100|unique:pgsql.dbo.d2_modalidad_laboral,nombre,' . $id,
            'estado' => 'required|in:ACTIVO,INACTIVO',
        ]);

        $user = Auth::user()->id_emp ?? Auth::id();
        $anterior = ['nombre' => $m->nombre, 'estado' => $m->estado];

        $m->update([
            'nombre'     => trim($request->nombre),
            'estado'     => $request->estado,
            'updated_at' => now(),
            'updated_by' => $user,
        ]);

        AuditoriaService::log('dbo.d2_modalidad_laboral', $m->id, 'ACTUALIZAR', $anterior,
            ['nombre' => $m->nombre, 'estado' => $m->estado], $request, "Edición modalidad laboral {$m->nombre}");

        return response()->json($m);
    }
}
