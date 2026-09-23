<?php
namespace App\Http\Controllers\Comisiones;

use App\Http\Controllers\Controller;
use App\Models\ComCoeficientePais;
use Illuminate\Http\Request;

class CoeficientePaisController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR'];

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        return response()->json(
            ComCoeficientePais::orderBy('region')->orderBy('pais')->get()
        );
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            'pais'        => 'required|string|max:100|unique:pgsql.dbo.com_coeficiente_pais,pais',
            'region'      => 'required|string|max:50',
            'coeficiente' => 'required|numeric|min:0',
        ]);

        $p = ComCoeficientePais::create([
            'pais'        => strtoupper($request->pais),
            'region'      => strtoupper($request->region),
            'coeficiente' => $request->coeficiente,
            'activo'      => true,
        ]);

        return response()->json($p, 201);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $p = ComCoeficientePais::findOrFail($id);

        $request->validate([
            'coeficiente' => 'sometimes|numeric|min:0',
            'activo'      => 'sometimes|boolean',
        ]);

        $p->update($request->only('pais', 'region', 'coeficiente', 'activo'));

        return response()->json($p);
    }
}
