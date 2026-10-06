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
                ->orderBy("id")
                ->get()
        );
    }

    // Asignar supervisor a un area.
    // Regla (2026-10-06): un área SIN padre (padre_id vacío, excepto la 999 del sistema, ej. PRESIDENCIA)
    // puede tener hasta 2 supervisores, ambos pertenecientes a esa área, y cualquiera de los dos aprueba.
    // El resto de áreas conserva el comportamiento de siempre: un solo supervisor, "Asignar" lo reemplaza.
    // "Principal" = la fila más antigua (menor id) del área.
    //  - con `id`  → edita únicamente esa fila (cambia la persona).
    //  - sin `id`  → área vacía: crea | área raíz con 1: agrega la segunda | área con padre con 1: reemplaza.
    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            "id_depto"      => "required|integer",
            "id_supervisor" => "required|string",
            "id"            => "nullable|integer",
        ]);

        $depto  = Departamento::findOrFail($request->id_depto);
        $esRaiz = empty($depto->padre_id) && (int) $depto->id_depto !== 999;
        $filas  = Supervisor::where("id_depto", $depto->id_depto)->orderBy("id")->get();
        $sup    = Empleado::findOrFail($request->id_supervisor);
        $nombreDepto = $depto->nombre_depto ?? $depto->id_depto;

        // El segundo supervisor de un área raíz debe pertenecer a esa área
        $validarPertenencia = function () use ($sup, $depto, $nombreDepto) {
            if ((int) $sup->id_depto !== (int) $depto->id_depto) {
                abort(422, "El segundo supervisor debe pertenecer al departamento {$nombreDepto} (revisa el departamento en su ficha de empleado).");
            }
        };

        $fila     = null;
        $anterior = null;
        $accion   = 'CREAR';
        $verbo    = 'Asignación';

        if ($request->filled("id")) {
            // Editar una fila concreta
            $fila = $filas->firstWhere("id", (int) $request->id);
            if (!$fila || (int) $fila->id_depto !== (int) $depto->id_depto) {
                abort(404, "El supervisor indicado no pertenece a esta área.");
            }
            if ($filas->where("id", "!=", $fila->id)->contains("id_supervisor", $request->id_supervisor)) {
                abort(422, "Esa persona ya es supervisor de esta área.");
            }
            if ($esRaiz && $filas->count() > 1) $validarPertenencia();
            $accion = 'ACTUALIZAR';
            $verbo  = 'Cambio';
        } elseif ($filas->isEmpty()) {
            // Primer supervisor del área: sin validación extra (comportamiento de siempre)
        } elseif ($filas->count() === 1) {
            if ($esRaiz) {
                if ($filas->first()->id_supervisor === $request->id_supervisor) {
                    abort(422, "Esa persona ya es supervisor de esta área.");
                }
                $validarPertenencia();
                $verbo = 'Asignación de segundo supervisor';
            } else {
                $fila   = $filas->first();   // área con padre: reemplaza al actual
                $accion = 'ACTUALIZAR';
                $verbo  = 'Cambio';
            }
        } else {
            abort(422, $esRaiz
                ? "El área {$nombreDepto} ya tiene 2 supervisores (máximo permitido). Elimina uno o edítalo."
                : "El área {$nombreDepto} tiene 2 supervisores y ya no es un área sin padre. Elimina uno antes de asignar otro.");
        }

        if ($fila) {
            $anterior = ["id_depto" => $fila->id_depto, "id_supervisor" => $fila->id_supervisor];
            $fila->update([
                "id_supervisor"  => $request->id_supervisor,
                "fecha_registro" => now(),
                "usuario"        => $request->user()->id_emp,
            ]);
            $supArea = $fila;
        } else {
            $supArea = Supervisor::create([
                "id_depto"       => $depto->id_depto,
                "id_supervisor"  => $request->id_supervisor,
                "fecha_registro" => now(),
                "usuario"        => $request->user()->id_emp,
            ]);
        }

        AuditoriaService::log('dbo.supervisor_area', $supArea->getKey(), $accion,
            $anterior,
            ["id_depto" => (int) $depto->id_depto, "id_supervisor" => $request->id_supervisor],
            $request,
            "{$verbo} de supervisor en {$nombreDepto}: "
                . trim(($sup->apellido_emp ?? '') . ' ' . ($sup->nombre_emp ?? '')));

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
