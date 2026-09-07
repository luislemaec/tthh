<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Razon;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RazonController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];

    public function index()
    {
        return response()->json(Razon::orderBy('descripcion')->get());
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            'descripcion' => 'required|string|max:30',
            'descontable' => 'required|in:SI,NO',
            'tipo_razon'  => 'nullable|string|max:20',
        ]);

        $ultimo = Razon::max('secuencial');
        $sec    = ($ultimo ?? 0) + 1;
        $user   = Auth::user()->id_emp ?? Auth::id();
        $now    = now();

        $razon = Razon::create([
            'secuencial'            => $sec,
            'descripcion'           => strtoupper($request->descripcion),
            'descontable'           => $request->descontable,
            'tipo_razon'            => strtoupper($request->tipo_razon ?? ''),
            'nomina'                => $request->nomina ?? 'NO',
            'nomenclatura'          => strtoupper($request->nomenclatura ?? ''),
            'leyenda_justificacion' => $request->leyenda_justificacion ?? null,
            'estado'                => 'ACTIVO',
            'created_at'            => $now,
            'created_by'            => $user,
            'updated_at'            => $now,
            'updated_by'            => $user,
        ]);

        AuditoriaService::log('dbo.d2_razon', $razon->secuencial, 'CREAR', null,
            ['descripcion' => $razon->descripcion, 'descontable' => $razon->descontable],
            $request, "Creación razón {$razon->descripcion}");

        return response()->json($razon, 201);
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $razon = Razon::findOrFail($id);
        $request->validate([
            'descripcion' => 'required|string|max:30',
            'descontable' => 'required|in:SI,NO',
        ]);

        $user = Auth::user()->id_emp ?? Auth::id();
        $anterior = ['descripcion' => $razon->descripcion, 'descontable' => $razon->descontable];

        $razon->update([
            'descripcion'           => strtoupper($request->descripcion),
            'descontable'           => $request->descontable,
            'tipo_razon'            => strtoupper($request->tipo_razon ?? $razon->tipo_razon),
            'nomenclatura'          => strtoupper($request->nomenclatura ?? $razon->nomenclatura),
            'leyenda_justificacion' => $request->leyenda_justificacion ?? $razon->leyenda_justificacion,
            'updated_at'            => now(),
            'updated_by'            => $user,
        ]);

        AuditoriaService::log('dbo.d2_razon', $razon->secuencial, 'ACTUALIZAR', $anterior,
            ['descripcion' => $razon->descripcion, 'descontable' => $razon->descontable],
            $request, "Edición razón {$razon->descripcion}");

        return response()->json($razon);
    }

    public function inactivar(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $razon = Razon::findOrFail($id);
        $user  = Auth::user()->id_emp ?? Auth::id();

        $razon->update([
            'estado'     => 'INACTIVO',
            'updated_at' => now(),
            'updated_by' => $user,
        ]);

        AuditoriaService::log('dbo.d2_razon', $razon->secuencial, 'DESACTIVAR',
            ['estado' => 'ACTIVO'], ['estado' => 'INACTIVO'], $request, "Inactivación razón {$razon->descripcion}");

        return response()->json(['message' => 'Razón marcada como inactiva.']);
    }
}
