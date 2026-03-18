<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Departamento;
use App\Models\EmpleadoMail;
use Illuminate\Http\Request;

class EmpleadoController extends Controller
{
    // GET /api/empleados
    public function index(Request $request)
    {
        $query = Empleado::with(["departamento", "emails"]);

        if ($request->filled("buscar")) {
            $b = $request->buscar;
            $query->where(function ($q) use ($b) {
                $q->where("nombre_emp",     "ilike", "%$b%")
                  ->orWhere("apellido_emp",  "ilike", "%$b%")
                  ->orWhere("identificacion","ilike", "%$b%");
            });
        }

        if ($request->filled("departamento_id")) {
            $query->where("id_depto", $request->departamento_id);
        }

        if ($request->filled("estado")) {
            $query->where("estado", strtoupper($request->estado));
        }

        $perPage = $request->get("per_page", 10);
        $data    = $query->orderBy("apellido_emp")->orderBy("nombre_emp")
                         ->paginate($perPage);

        return response()->json($data);
    }

    // GET /api/empleados/{id}
    public function show($id)
    {
        $emp = Empleado::with(["departamento", "emails"])->findOrFail($id);
        return response()->json($emp);
    }

    // Generar id_emp correlativo
    private function generarIdEmp(): string
    {
        $ultimo = Empleado::orderByRaw("id_emp DESC")->value("id_emp");
        $numero = $ultimo ? ((int) $ultimo) + 1 : 1;
        return str_pad($numero, 5, "0", STR_PAD_LEFT);
    }

    // POST /api/empleados
    public function store(Request $request)
    {
        $request->validate([
            "identificacion" => "required|string|max:15",
            "nombre_emp"     => "required|string|max:240",
            "apellido_emp"   => "required|string|max:240",
            "id_depto"       => "required|integer",
            "fecha_ingreso"  => "nullable|date",
            "sueldo"         => "nullable|numeric|min:0",
            "estado"         => "nullable|string|max:10",
            "email"          => "nullable|email",
        ]);

        $emp = Empleado::create([
            "id_emp"         => $this->generarIdEmp(),
            "identificacion" => $request->identificacion,
            "nombre_emp"     => strtoupper($request->nombre_emp),
            "apellido_emp"   => strtoupper($request->apellido_emp),
            "id_depto"       => $request->id_depto,
            "estado"         => strtoupper($request->estado ?? "ACTIVO"),
            "tipo_contrato"      => $request->tipo_contrato,
            "jornada_id"     => $request->jornada_id,
            "fecha_ingreso"  => $request->fecha_ingreso,
            "sueldo"         => $request->sueldo,
            "nivel"          => $request->nivel,
            "ubicacion"      => $request->ubicacion,
            "cargo_empleado" => $request->cargo_empleado,
            "telefono"       => $request->telefono,
            "calle_y_numero" => $request->calle_y_numero,
        ]);
	$emp->password = bcrypt($request->identificacion);
	$emp->save();

        // Guardar email si se proporcionó
        if ($request->filled("email")) {
            EmpleadoMail::create([
                "id_emp" => $emp->id_emp,
                "mail"   => $request->email,
                "estado" => "ACTIVO",
            ]);
        }

        return response()->json($emp->load(["departamento", "emails"]), 201);
    }

    // PUT /api/empleados/{id}
    public function update(Request $request, $id)
    {
        $emp = Empleado::findOrFail($id);

        $request->validate([
            "identificacion" => "nullable|string|max:15",
            "nombre_emp"     => "nullable|string|max:240",
            "apellido_emp"   => "nullable|string|max:240",
            "id_depto"       => "nullable|integer",
            "fecha_ingreso"  => "nullable|date",
            "sueldo"         => "nullable|numeric|min:0",
            "estado"         => "nullable|string|max:10",
            "email"          => "nullable|email",
        ]);

        $emp->update([
            "identificacion" => $request->identificacion  ?? $emp->identificacion,
            "nombre_emp"     => $request->filled("nombre_emp")    ? strtoupper($request->nombre_emp)    : $emp->nombre_emp,
            "apellido_emp"   => $request->filled("apellido_emp")  ? strtoupper($request->apellido_emp)  : $emp->apellido_emp,
            "id_depto"       => $request->id_depto        ?? $emp->id_depto,
            "estado"         => $request->filled("estado")        ? strtoupper($request->estado)        : $emp->estado,
            "tipo_contrato"      => $request->tipo_contrato       ?? $emp->tipo_contrato,
            "jornada_id"     => $request->jornada_id      ?? $emp->jornada_id,
            "fecha_ingreso"  => $request->fecha_ingreso   ?? $emp->fecha_ingreso,
            "fecha_salida"   => $request->fecha_salida    ?? $emp->fecha_salida,
            "sueldo"         => $request->sueldo          ?? $emp->sueldo,
            "nivel"          => $request->nivel           ?? $emp->nivel,
            "ubicacion"      => $request->ubicacion       ?? $emp->ubicacion,
            "cargo_empleado" => $request->cargo_empleado  ?? $emp->cargo_empleado,
            "telefono"       => $request->telefono        ?? $emp->telefono,
            "calle_y_numero" => $request->calle_y_numero  ?? $emp->calle_y_numero,
        ]);

        // Actualizar email
        if ($request->filled("email")) {
            // Desactivar emails anteriores
            EmpleadoMail::where("id_emp", $emp->id_emp)->update(["estado" => "INACTIVO"]);
            // Crear nuevo email activo
            EmpleadoMail::create([
                "id_emp" => $emp->id_emp,
                "mail"   => $request->email,
                "estado" => "ACTIVO",
            ]);
        }

        return response()->json($emp->load(["departamento", "emails"]));
    }

    // DELETE /api/empleados/{id}
    public function destroy($id)
    {
        $emp = Empleado::findOrFail($id);
        $emp->update(["estado" => "INACTIVO"]);
        return response()->json(["message" => "Empleado desactivado correctamente."]);
    }

    // GET /api/departamentos
    public function departamentos()
    {
        $deps = Departamento::orderBy("nombre_depto")->get(["id_depto", "nombre_depto"]);
        return response()->json($deps);
    }
}
