<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Razon;
use Illuminate\Http\Request;

class RazonController extends Controller
{
    public function index()
    {
        return response()->json(Razon::orderBy('descripcion')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'descripcion' => 'required|string|max:30',
            'descontable' => 'required|in:SI,NO',
            'tipo_razon'  => 'nullable|string|max:20',
        ]);

        $ultimo = Razon::max('secuencial');
        $sec    = ($ultimo ?? 0) + 1;

        $razon = Razon::create([
            'secuencial'  => $sec,
            'descripcion' => strtoupper($request->descripcion),
            'descontable' => $request->descontable,
            'tipo_razon'  => strtoupper($request->tipo_razon ?? ''),
            'nomina'      => $request->nomina ?? 'NO',
            'nomenclatura'=> strtoupper($request->nomenclatura ?? ''),
            'leyenda_justificacion' => $request->leyenda_justificacion ?? null,
        ]);

        return response()->json($razon, 201);
    }

    public function update(Request $request, $id)
    {
        $razon = Razon::findOrFail($id);
        $request->validate([
            'descripcion' => 'required|string|max:30',
            'descontable' => 'required|in:SI,NO',
        ]);

        $razon->update([
            'descripcion' => strtoupper($request->descripcion),
            'descontable' => $request->descontable,
            'tipo_razon'  => strtoupper($request->tipo_razon ?? $razon->tipo_razon),
            'nomenclatura'=> strtoupper($request->nomenclatura ?? $razon->nomenclatura),
            'leyenda_justificacion' => $request->leyenda_justificacion ?? $razon->leyenda_justificacion,
        ]);

        return response()->json($razon);
    }

    public function destroy($id)
    {
        Razon::findOrFail($id)->delete();
        return response()->json(['message' => 'Razón eliminada.']);
    }
}
