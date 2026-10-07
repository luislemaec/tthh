<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];
    private const ROLES_ACCIONES = ['ADMINISTRADOR', 'TALENTO HUMANO', 'TH ACCIONES PERSONAL'];

    // Listar todos los parámetros
    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        return response()->json(
            Configuracion::orderBy("concepto")->get()
        );
    }

    // Actualizar valor de un parámetro
    public function update(Request $request, $concepto)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            "valor"       => "required|string|max:150",
            "descripcion" => "nullable|string|max:300",
        ]);
        $this->validarUbicacion($request, $concepto);

        $config = Configuracion::findOrFail($concepto);
        $valorAnterior = $config->valor;
        $config->update([
            "valor"       => $request->valor,
            "descripcion" => $request->descripcion,
            "updated_at"  => now(),
            "updated_by"  => $request->user()->id_emp,
        ]);

        AuditoriaService::log('dbo.d2_configuracion', 0, 'ACTUALIZAR',
            ['concepto' => $concepto, 'valor' => $valorAnterior],
            ['concepto' => $concepto, 'valor' => $request->valor],
            $request, "Cambio de configuración: {$concepto}");

        return response()->json($config);
    }

    // Crear nuevo parámetro
    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            "concepto"    => "required|string|max:120",
            "valor"       => "required|string|max:150",
            "descripcion" => "nullable|string|max:300",
        ]);
        $this->validarUbicacion($request, $request->concepto);

        $existe = Configuracion::where("concepto", $request->concepto)->exists();
        if ($existe) {
            return response()->json([
                "message" => "El parámetro ya existe"
            ], 422);
        }

        $config = Configuracion::create([
            "concepto"    => $request->concepto,
            "valor"       => $request->valor,
            "descripcion" => $request->descripcion,
            "created_at"  => now(),
            "created_by"  => $request->user()->id_emp,
            "updated_at"  => now(),
            "updated_by"  => $request->user()->id_emp,
        ]);

        return response()->json($config, 201);
    }

    private function validarUbicacion(Request $request, string $concepto): void
    {
        $reglas = [
            'app_latitud' => 'numeric|between:-90,90',
            'app_longitud' => 'numeric|between:-180,180',
            'app_radio_m' => 'numeric|gt:0',
            'app_precision_m' => 'numeric|gt:0',
            'app_antiguedad_s' => 'integer|gt:0',
        ];
        if (isset($reglas[$concepto])) {
            $request->validate(['valor' => $reglas[$concepto]]);
        }
    }

    // Eliminar parámetro
    public function destroy(Request $request, $concepto)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        Configuracion::findOrFail($concepto)->delete();
        return response()->json(["message" => "Parámetro eliminado correctamente"]);
    }

    // Firmantes de acciones de personal (para pre-llenar el formulario)
    public function firmantes(Request $request)
    {
        $this->requireRole($request, self::ROLES_ACCIONES);
        $cfg = Configuracion::whereIn("concepto", [
            "FIRMANTE_TH_NOMBRE", "FIRMANTE_TH_CARGO",
            "FIRMANTE_AUTORIDAD_NOMBRE", "FIRMANTE_AUTORIDAD_CARGO",
        ])->pluck("valor", "concepto");

        return response()->json([
            "firmante_th_nombre"        => $cfg["FIRMANTE_TH_NOMBRE"]        ?? "",
            "firmante_th_cargo"         => $cfg["FIRMANTE_TH_CARGO"]         ?? "",
            "firmante_autoridad_nombre" => $cfg["FIRMANTE_AUTORIDAD_NOMBRE"] ?? "",
            "firmante_autoridad_cargo"  => $cfg["FIRMANTE_AUTORIDAD_CARGO"]  ?? "",
        ]);
    }

    // Cargar parámetros base
    public function cargarParametrosBase(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $parametros = [
            ['concepto' => 'app_latitud', 'valor' => '-0.1805373', 'descripcion' => 'Latitud de la sede para marcación móvil'],
            ['concepto' => 'app_longitud', 'valor' => '-78.4892070', 'descripcion' => 'Longitud de la sede para marcación móvil'],
            ['concepto' => 'app_radio_m', 'valor' => '50', 'descripcion' => 'Radio autorizado de marcación móvil, metros'],
            ['concepto' => 'app_precision_m', 'valor' => '25', 'descripcion' => 'Precisión máxima del GPS, metros'],
            ['concepto' => 'app_antiguedad_s', 'valor' => '30', 'descripcion' => 'Antigüedad máxima de la ubicación, segundos'],
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
            ["concepto" => "RUC_PATRONAL",            "valor" => "1768174610001"],
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
