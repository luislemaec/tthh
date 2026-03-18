<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    // Listar todos los parámetros
    public function index()
    {
        return response()->json(
            Configuracion::orderBy("concepto")->get()
        );
    }

    // Actualizar valor de un parámetro
    public function update(Request $request, $concepto)
    {
        $request->validate([
            "valor" => "required|string|max:150",
        ]);

        $config = Configuracion::findOrFail($concepto);
        $config->update(["valor" => $request->valor]);

        return response()->json($config);
    }

    // Crear nuevo parámetro
    public function store(Request $request)
    {
        $request->validate([
            "concepto" => "required|string|max:120",
            "valor"    => "required|string|max:150",
        ]);

        $existe = Configuracion::where("concepto", $request->concepto)->exists();
        if ($existe) {
            return response()->json([
                "message" => "El parámetro ya existe"
            ], 422);
        }

        $config = Configuracion::create([
            "concepto" => $request->concepto,
            "valor"    => $request->valor,
        ]);

        return response()->json($config, 201);
    }

    // Eliminar parámetro
    public function destroy($concepto)
    {
        Configuracion::findOrFail($concepto)->delete();
        return response()->json(["message" => "Parámetro eliminado correctamente"]);
    }

    // Cargar parámetros base
    public function cargarParametrosBase()
    {
        $parametros = [
            ["concepto" => "Tiempo castigo lunch",   "valor" => "30"],
            ["concepto" => "FLOREQUISA CONSUMO",      "valor" => "NO"],
            ["concepto" => "HCC CONSUMO",             "valor" => "NO"],
            ["concepto" => "tolerancia_entrada",      "valor" => "5"],
            ["concepto" => "tolerancia_lunch",        "valor" => "5"],
            ["concepto" => "hora_inicio_jornada",     "valor" => "08:00"],
            ["concepto" => "dias_vacaciones_anio",    "valor" => "30"],
            ["concepto" => "correo_notificaciones",   "valor" => "rrhh@institucion.gob.ec"],
            ["concepto" => "nombre_institucion",      "valor" => "CONSEJO DE COMUNICACION"],
            ["concepto" => "ubicacion_default",       "valor" => "Quito"],
        ];

        $insertados = 0;
        foreach ($parametros as $p) {
            $existe = Configuracion::where("concepto", $p["concepto"])->exists();
            if (!$existe) {
                Configuracion::create($p);
                $insertados++;
            }
        }

        return response()->json([
            "message"    => "$insertados parámetros cargados correctamente",
            "insertados" => $insertados,
        ]);
    }
}
