<?php
namespace App\Http\Controllers;

use App\Models\Jornada;
use Illuminate\Http\Request;

class JornadaController extends Controller
{
    // Listar todas las jornadas
    public function index()
    {
        $jornadas = Jornada::orderBy("id_jornada")->get();
        return response()->json($jornadas);
    }

    // Crear nueva jornada
    public function store(Request $request)
    {
        $request->validate([
            "id_jornada"  => "required|integer|unique:dbo.d2_jornada,id_jornada",
            "descripcion" => "required|string|max:50",
        ]);

        $jornada = Jornada::create($request->all());
        return response()->json($jornada, 201);
    }

    // Ver una jornada
    public function show($id)
    {
        $jornada = Jornada::findOrFail($id);
        return response()->json($jornada);
    }

    // Actualizar jornada
    public function update(Request $request, $id)
    {
        $jornada = Jornada::findOrFail($id);
        $request->validate([
            "descripcion" => "required|string|max:50",
        ]);
        $jornada->update($request->all());
        return response()->json($jornada);
    }

    // Eliminar jornada
    public function destroy($id)
    {
        $jornada = Jornada::findOrFail($id);
        $jornada->delete();
        return response()->json(["message" => "Jornada eliminada correctamente"]);
    }
}
