<?php
namespace App\Http\Controllers;

use App\Models\AdminOpcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpcionController extends Controller
{
    // Listar todas las opciones
    public function index()
    {
        $opciones = AdminOpcion::orderBy("orden_categoria")
            ->orderBy("secuencia")
            ->get();
        return response()->json($opciones);
    }

    // Crear nueva opción
    public function store(Request $request)
    {
        $request->validate([
	    "id" => "required|string|max:10|unique:pgsql.dbo.admin_opcion,id",
            "descripcion" => "required|string|max:50",
            "url"         => "required|string|max:50",
            "categoria"   => "required|string|max:20",
            "orden_categoria" => "required|integer",
            "secuencia"   => "required|integer",
        ]);

        $opcion = AdminOpcion::create([
            "id"              => strtoupper($request->id),
            "descripcion"     => $request->descripcion,
            "url"             => $request->url,
            "categoria"       => strtoupper($request->categoria),
            "orden_categoria" => $request->orden_categoria,
            "secuencia"       => $request->secuencia,
            "estado"          => true,
            "padre"           => $request->padre,
        ]);

        return response()->json($opcion, 201);
    }

    // Ver una opción
    public function show($id)
    {
        $opcion = AdminOpcion::findOrFail($id);
        return response()->json($opcion);
    }

    // Actualizar opción
    public function update(Request $request, $id)
    {
        $opcion = AdminOpcion::findOrFail($id);
        $request->validate([
            "descripcion"     => "required|string|max:50",
            "url"             => "required|string|max:50",
            "categoria"       => "required|string|max:20",
            "orden_categoria" => "required|integer",
            "secuencia"       => "required|integer",
        ]);

        $opcion->update([
            "descripcion"     => $request->descripcion,
            "url"             => $request->url,
            "categoria"       => strtoupper($request->categoria),
            "orden_categoria" => $request->orden_categoria,
            "secuencia"       => $request->secuencia,
            "padre"           => $request->padre,
        ]);

        return response()->json($opcion);
    }

    // Activar o desactivar opción
    public function toggleEstado($id)
    {
        $opcion = AdminOpcion::findOrFail($id);
        $opcion->update(["estado" => !$opcion->estado]);
        return response()->json([
            "message" => $opcion->estado ? "Opción activada" : "Opción desactivada",
            "estado"  => $opcion->estado,
        ]);
    }

    // Eliminar opción
    public function destroy($id)
    {
        $opcion = AdminOpcion::findOrFail($id);
        // Eliminar asignaciones a roles primero
        DB::table("dbo.admin_rol_opcion")->where("id_opcion", $id)->delete();
        $opcion->delete();
        return response()->json(["message" => "Opción eliminada correctamente"]);
    }

    // Listar categorías existentes
    public function categorias()
    {	
    	$categorias = AdminOpcion::select("categoria")
        ->distinct()
        ->orderBy("categoria")
        ->pluck("categoria");
    return response()->json($categorias);
    }
}
