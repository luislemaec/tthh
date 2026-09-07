<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ListaFecha;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;

class CalendarioController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];

    // Listar fechas por año
    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $anio = $request->get("anio", date("Y"));

        $fechas = ListaFecha::whereYear("fecha", $anio)
            ->orderBy("fecha")
            ->get()
            ->map(function ($f) {
                return [
                    "fecha"     => substr($f->fecha, 0, 10),
                    "factor"    => $f->factor,
                    "tipo"      => trim($f->tipo),
                    "color"     => trim($f->color),
                    "hora_desde"=> substr($f->hora_desde, 11, 5),
                    "hora_hasta"=> substr($f->hora_hasta, 11, 5),
                    "ubicacion" => trim($f->ubicacion),
                    "hora_25"   => $f->hora_25,
                ];
            });

        return response()->json($fechas);
    }

    // Crear nueva fecha
    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            "fecha"     => "required|date",
            "tipo"      => "required|string|max:10",
            "factor"    => "required|numeric",
            "color"     => "required|string|max:10",
            "hora_desde"=> "required|string",
            "hora_hasta"=> "required|string",
            "ubicacion" => "required|string|max:120",
        ]);

        // Verificar si ya existe
        $existe = ListaFecha::where("fecha", $request->fecha)
            ->where("ubicacion", $request->ubicacion)
            ->exists();

        if ($existe) {
            return response()->json([
                "message" => "Ya existe una fecha registrada para ese día y ubicación"
            ], 422);
        }

        $fecha = ListaFecha::create([
            "fecha"     => $request->fecha,
            "factor"    => $request->factor,
            "tipo"      => strtoupper($request->tipo),
            "color"     => strtolower($request->color),
            "hora_desde"=> $request->fecha . " " . $request->hora_desde . ":00",
            "hora_hasta"=> $request->fecha . " " . $request->hora_hasta . ":00",
            "ubicacion" => $request->ubicacion,
            "hora_25"   => $request->hora_25 ?? 0,
            "transmitio"=> "NO",
        ]);

        AuditoriaService::log('dbo.d2_lista_fecha', 0, 'CREAR', null,
            ['fecha' => $request->fecha, 'tipo' => $fecha->tipo, 'ubicacion' => $fecha->ubicacion],
            $request, "Creación fecha especial {$request->fecha} ({$fecha->tipo}, {$fecha->ubicacion})");

        return response()->json($fecha, 201);
    }

    // Actualizar fecha (permite además reasignar la fecha misma, no solo tipo/factor/horas)
    public function update(Request $request, $fecha, $ubicacion)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $registro = ListaFecha::where("fecha", $fecha)
            ->where("ubicacion", $ubicacion)
            ->firstOrFail();

        $request->validate([
            "fecha"     => "nullable|date",
            "tipo"      => "required|string|max:10",
            "factor"    => "required|numeric",
            "color"     => "required|string|max:10",
            "hora_desde"=> "required|string",
            "hora_hasta"=> "required|string",
        ]);

        $nuevaFecha = $request->filled("fecha") ? $request->fecha : $fecha;

        if ($nuevaFecha !== $fecha) {
            $existe = ListaFecha::where("fecha", $nuevaFecha)
                ->where("ubicacion", $ubicacion)
                ->exists();

            if ($existe) {
                return response()->json([
                    "message" => "Ya existe una fecha registrada para ese día y ubicación"
                ], 422);
            }
        }

        $anterior = ['fecha' => $fecha, 'tipo' => $registro->tipo, 'factor' => $registro->factor];

        $registro->update([
            "fecha"     => $nuevaFecha,
            "factor"    => $request->factor,
            "tipo"      => strtoupper($request->tipo),
            "color"     => strtolower($request->color),
            "hora_desde"=> $nuevaFecha . " " . $request->hora_desde . ":00",
            "hora_hasta"=> $nuevaFecha . " " . $request->hora_hasta . ":00",
            "hora_25"   => $request->hora_25 ?? 0,
        ]);

        AuditoriaService::log('dbo.d2_lista_fecha', 0, 'ACTUALIZAR', $anterior,
            ['fecha' => $nuevaFecha, 'tipo' => $registro->tipo, 'factor' => $registro->factor],
            $request, "Edición fecha especial {$fecha} → {$nuevaFecha} ({$ubicacion})");

        return response()->json($registro);
    }

    // Eliminar fecha
    public function destroy(Request $request, $fecha, $ubicacion)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $registro = ListaFecha::where("fecha", $fecha)
            ->where("ubicacion", $ubicacion)
            ->firstOrFail();
        $tipo = $registro->tipo;
        $registro->delete();

        AuditoriaService::log('dbo.d2_lista_fecha', 0, 'ELIMINAR',
            ['fecha' => $fecha, 'tipo' => $tipo, 'ubicacion' => $ubicacion], null,
            $request, "Eliminación fecha especial {$fecha} ({$ubicacion})");

        return response()->json(["message" => "Fecha eliminada correctamente"]);
    }

    // Cargar feriados nacionales de Ecuador automáticamente
    public function cargarFeriadosEcuador(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $anio     = (int) $request->get("anio", date("Y"));
        $ubicacion = $request->get("ubicacion", "Quito");

        // Carnaval y Viernes Santo son fechas móviles (dependen de la Pascua) — se calculan
        // con easter_days() en vez de dejarlas fijas, para que coincidan con el año seleccionado.
        $pascua = new \DateTime("$anio-03-21");
        $pascua->modify('+' . easter_days($anio) . ' days');

        $carnavalLunes  = (clone $pascua)->modify('-48 days')->format('Y-m-d');
        $carnavalMartes = (clone $pascua)->modify('-47 days')->format('Y-m-d');
        $viernesSanto   = (clone $pascua)->modify('-2 days')->format('Y-m-d');

        $feriados = [
            ["fecha" => "$anio-01-01", "descripcion" => "Año Nuevo"],
            ["fecha" => $carnavalLunes,  "descripcion" => "Carnaval"],
            ["fecha" => $carnavalMartes, "descripcion" => "Carnaval"],
            ["fecha" => $viernesSanto,   "descripcion" => "Viernes Santo"],
            ["fecha" => "$anio-05-01", "descripcion" => "Día del Trabajo"],
            ["fecha" => "$anio-05-24", "descripcion" => "Batalla de Pichincha"],
            ["fecha" => "$anio-08-10", "descripcion" => "Primer Grito Independencia"],
            ["fecha" => "$anio-10-09", "descripcion" => "Independencia de Guayaquil"],
            ["fecha" => "$anio-11-02", "descripcion" => "Día de Difuntos"],
            ["fecha" => "$anio-11-03", "descripcion" => "Independencia de Cuenca"],
            ["fecha" => "$anio-12-06", "descripcion" => "Fundación de Quito"],
            ["fecha" => "$anio-12-25", "descripcion" => "Navidad"],
        ];

        $insertados = 0;
        foreach ($feriados as $f) {
            $existe = ListaFecha::where("fecha", $f["fecha"])
                ->where("ubicacion", $ubicacion)
                ->exists();

            if (!$existe) {
                ListaFecha::create([
                    "fecha"     => $f["fecha"],
                    "factor"    => 2.00,
                    "tipo"      => "FERIADO",
                    "color"     => "red",
                    "hora_desde"=> $f["fecha"] . " 00:00:00",
                    "hora_hasta"=> $f["fecha"] . " 23:59:00",
                    "ubicacion" => $ubicacion,
                    "hora_25"   => 0,
                    "transmitio"=> "NO",
                ]);
                $insertados++;
            }
        }

        AuditoriaService::log('dbo.d2_lista_fecha', 0, 'CARGAR_FERIADOS', null,
            ['anio' => $anio, 'ubicacion' => $ubicacion, 'insertados' => $insertados],
            $request, "Carga automática de feriados Ecuador {$anio} ({$ubicacion})");

        return response()->json([
            "message"    => "$insertados feriados cargados correctamente",
            "insertados" => $insertados,
        ]);
    }
}
