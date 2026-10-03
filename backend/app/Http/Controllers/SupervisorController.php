<?php
namespace App\Http\Controllers;

use App\Models\Supervisor;
use App\Models\Empleado;
use App\Models\Departamento;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;

class SupervisorController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];

    // Listar supervisores por area
    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        return response()->json(
            Supervisor::with(["departamento", "supervisor.departamento"])
                ->orderBy("id_depto")
                ->get()
        );
    }

    // Asignar supervisor a un area
    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            "id_depto"      => "required|integer",
            "id_supervisor" => "required|string",
        ]);


        $previo   = Supervisor::where("id_depto", $request->id_depto)->first();
        $anterior = $previo ? ["id_depto" => $previo->id_depto, "id_supervisor" => $previo->id_supervisor] : null;

        // Si ya existe actualiza, si no crea
        $supArea = Supervisor::updateOrCreate(
            ["id_depto" => $request->id_depto],
            [
                "id_supervisor"  => $request->id_supervisor,
                "fecha_registro" => now(),
                "usuario"        => $request->user()->id_emp,
            ]
        );

        $depto = Departamento::find($request->id_depto);
        $sup   = Empleado::find($request->id_supervisor);
        AuditoriaService::log('dbo.supervisor_area', $supArea->getKey(), $previo ? 'ACTUALIZAR' : 'CREAR',
            $anterior,
            ["id_depto" => (int) $request->id_depto, "id_supervisor" => $request->id_supervisor],
            $request,
            ($previo ? 'Cambio' : 'Asignación') . ' de supervisor en ' . ($depto->nombre_depto ?? $request->id_depto)
                . ': ' . trim(($sup->apellido_emp ?? '') . ' ' . ($sup->nombre_emp ?? '')));

        return response()->json(
            $supArea->load(["departamento", "supervisor.departamento"]), 201
        );
    }

    // Eliminar supervisor de un area
    public function destroy(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $supArea = Supervisor::with("departamento")->findOrFail($id);
        $datos   = ["id_depto" => $supArea->id_depto, "id_supervisor" => $supArea->id_supervisor];
        $nombre  = $supArea->departamento->nombre_depto ?? $supArea->id_depto;
        $supArea->delete();

        AuditoriaService::log('dbo.supervisor_area', $id, 'ELIMINAR', $datos, null, $request,
            'Eliminación de supervisor del área ' . $nombre);

        return response()->json(["message" => "Supervisor eliminado del area correctamente"]);
    }

    // Obtener supervisor de un empleado con logica padre-hijo
    public function supervisorDeEmpleado(Request $request, $id_emp)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $empleado = Empleado::with("departamento")->findOrFail($id_emp);
        $depto    = $empleado->departamento;
        $intentos = 0;

        while ($depto && $intentos < 5) {
            // Buscar supervisor del departamento
            $supArea = Supervisor::with("supervisor")
                ->where("id_depto", $depto->id_depto)
                ->first();

            if ($supArea) {
                return response()->json([
                    "supervisor" => $supArea->supervisor,
                    "area"       => $depto->nombre_depto,
                ]);
            }

            // Subir al departamento padre
            if (!$depto->padre_id) break;
            $depto = Departamento::find($depto->padre_id);
            $intentos++;
        }

        return response()->json(null);
    }

    // Listar departamentos con y sin supervisor
    public function departamentosConSupervisor(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $deptos = Departamento::with([
            "supervisorArea.supervisor"
        ])->orderBy("nombre_depto")->get();

        return response()->json($deptos);
    }
}
