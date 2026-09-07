<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabTurno;
use App\Models\Turno;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;

class TurnoController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];

    public function index()
    {
        return response()->json(
            CabTurno::with("horarios")->orderBy("descripcion")->get()
        );
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            "descripcion"    => "required|string|max:30",
            "horas_normales" => "nullable|numeric",
            "color"          => "nullable|string|max:20",
        ]);

        $ultimo = CabTurno::max("id_turno");
        $id     = ($ultimo ?? 0) + 1;

        $turno = CabTurno::create([
            "id_turno"      => $id,
            "descripcion"   => strtoupper($request->descripcion),
            "color"         => $request->color ?? "#3b82f6",
            "horas_normales"=> $request->horas_normales ?? 8,
            "horas_25"      => $request->horas_25 ?? 0,
        ]);

        AuditoriaService::log('dbo.d2_cab_turno', $turno->id_turno, 'CREAR', null,
            ['descripcion' => $turno->descripcion], $request, "Creación turno {$turno->descripcion}");

        return response()->json($turno->load("horarios"), 201);
    }

    public function show($id)
    {
        return response()->json(
            CabTurno::with("horarios")->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $turno = CabTurno::findOrFail($id);
        $request->validate([
            "descripcion" => "required|string|max:30",
        ]);

        $anterior = ['descripcion' => $turno->descripcion, 'horas_normales' => $turno->horas_normales];

        $turno->update([
            "descripcion"    => strtoupper($request->descripcion),
            "color"          => $request->color ?? $turno->color,
            "horas_normales" => $request->horas_normales ?? $turno->horas_normales,
            "horas_25"       => $request->horas_25 ?? $turno->horas_25,
        ]);

        AuditoriaService::log('dbo.d2_cab_turno', $turno->id_turno, 'ACTUALIZAR', $anterior,
            ['descripcion' => $turno->descripcion, 'horas_normales' => $turno->horas_normales],
            $request, "Edición turno {$turno->descripcion}");

        return response()->json($turno->load("horarios"));
    }

    public function destroy(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $turno = CabTurno::findOrFail($id);
        Turno::where("id_turno", $id)->delete();
        $turno->delete();

        AuditoriaService::log('dbo.d2_cab_turno', $id, 'ELIMINAR',
            ['descripcion' => $turno->descripcion], null, $request, "Eliminación turno {$turno->descripcion}");

        return response()->json(["message" => "Turno eliminado correctamente"]);
    }

    // Antes sin auditar — cambiar las horas de ENTRADA/SALIDA de un turno afecta de golpe el
    // cálculo de atrasos (ProcesarCuadre, spec 03) y la clasificación de horas extras (spec 08)
    // de todos los empleados que tengan ese turno asignado ese mes.
    public function guardarHorarios(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            "horarios"              => "required|array",
            "horarios.*.concepto"   => "required|string",
            "horarios.*.hora"       => "required|string",
        ]);

        $turno = CabTurno::findOrFail($id);

        $anterior = Turno::where("id_turno", $id)->get(['concepto', 'hora'])->toArray();

        Turno::where("id_turno", $id)->delete();

        foreach ($request->horarios as $horario) {
            // Convertir "08:00" a timestamp "1970-01-01 08:00:00"
            $hora = "1970-01-01 " . $horario["hora"] . ":00";

            Turno::create([
                "id_turno"   => $id,
                "concepto"   => strtoupper($horario["concepto"]),
                "hora"       => $hora,
                "id_jornada" => $horario["id_jornada"] ?? null,
            ]);
        }

        AuditoriaService::log('dbo.d2_turno', $id, 'ACTUALIZAR_HORARIOS',
            ['horarios' => $anterior], ['horarios' => $request->horarios],
            $request, "Edición horarios del turno {$turno->descripcion}");

        return response()->json(
            CabTurno::with("horarios")->find($id)
        );
    }
}
