<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Departamento;
use Illuminate\Http\Request;

class DepartamentoController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];

    public function index()
    {
        $deps = Departamento::with('padre')
            ->orderBy('padre_id')
            ->orderBy('nombre_depto')
            ->get();
        return response()->json($deps);
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            'id_depto'     => 'nullable|integer|unique:pgsql.dbo.ad_departamento,id_depto',
            'nombre_depto' => 'required|string|max:120',
            'padre_id'     => 'nullable|integer',
        ]);

        // Usar ID manual si se proporcionó, sino generar correlativo
        if ($request->filled('id_depto')) {
            $id = (int) $request->id_depto;
        } else {
            $ultimo = Departamento::where('id_depto', '!=', 999)->max('id_depto');
            $id     = ($ultimo ?? 0) + 1;
        }

        $usuario = auth()->user()->id_emp ?? null;

        $dep = Departamento::create([
            'id_depto'        => $id,
            'nombre_depto'    => strtoupper($request->nombre_depto),
            'centro_de_costo' => strtoupper($request->centro_de_costo ?? ''),
            'padre_id'        => $request->padre_id ?: null,
            'estado'          => 'ACTIVO',
            'created_at'      => now(),
            'created_by'      => $usuario,
            'updated_at'      => now(),
            'updated_by'      => $usuario,
        ]);

        return response()->json($dep->load('padre'), 201);
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $dep = Departamento::findOrFail($id);
        $request->validate([
            'nombre_depto' => 'required|string|max:120',
            'padre_id'     => 'nullable|integer',
        ]);

        // Evitar que un departamento sea su propio padre
        if ($request->padre_id == $id) {
            return response()->json(['message' => 'Un departamento no puede ser su propio padre.'], 422);
        }

        $dep->update([
            'nombre_depto'    => strtoupper($request->nombre_depto),
            'centro_de_costo' => strtoupper($request->centro_de_costo ?? $dep->centro_de_costo),
            'padre_id'        => $request->padre_id ?: null,
            'updated_at'      => now(),
            'updated_by'      => auth()->user()->id_emp ?? null,
        ]);

        return response()->json($dep->load('padre'));
    }

    public function inactivar(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $dep = Departamento::findOrFail($id);

        // Verificar que no tenga empleados activos
        if ($dep->empleados()->where('estado', 'ACTIVO')->exists()) {
            return response()->json([
                'message' => 'No se puede inactivar: tiene empleados activos asignados. Muévalos a otra área primero.'
            ], 422);
        }

        // Verificar que no tenga departamentos hijos activos
        if ($dep->hijos()->where('estado', 'ACTIVO')->exists()) {
            $hijos = $dep->hijos()->where('estado', 'ACTIVO')->pluck('nombre_depto')->implode(', ');
            return response()->json([
                'message' => "No se puede inactivar: tiene áreas hijas activas ($hijos). Inactívalas primero."
            ], 422);
        }

        $dep->update(['estado' => 'INACTIVO']);
        return response()->json(['message' => 'Departamento inactivado correctamente.']);
    }

    public function activar(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $dep = Departamento::findOrFail($id);
        $dep->update(['estado' => 'ACTIVO']);
        return response()->json(['message' => 'Departamento activado correctamente.']);
    }
}
