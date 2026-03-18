<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportacionController extends Controller
{
    // Descargar plantilla CSV
    public function plantilla()
    {
        $headers = [
            "Content-Type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=plantilla_empleados.csv",
        ];

        $columnas = [
            "identificacion", "nombre_emp", "apellido_emp",
            "id_depto", "estado", "fecha_ingreso",
            "cargo_empleado", "telefono", "tipo_contrato",
            "sueldo", "nivel", "calle_y_numero", "email"
        ];

        $ejemplo = [
            "1234567890", "JUAN CARLOS", "PEREZ GARCIA",
            "16", "ACTIVO", "2020-01-15",
            "ANALISTA", "0991234567", "101",
            "1500.00", "1", "Av. Amazonas 123", "juan@correo.com"
        ];

        $callback = function() use ($columnas, $ejemplo) {
            $file = fopen("php://output", "w");
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $columnas);
            fputcsv($file, $ejemplo);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Vista previa del CSV
    public function preview(Request $request)
    {
        $request->validate([
            "archivo" => "required|file|mimes:csv,txt|max:5120",
        ]);

        $filas   = [];
        $errores = [];
        $archivo = $request->file("archivo");
        $handle  = fopen($archivo->getPathname(), "r");
        $cabeceras = null;
        $fila_num  = 0;

        while (($fila = fgetcsv($handle, 1000, ",")) !== false) {
            if ($fila_num === 0) {
                $cabeceras = array_map("trim", $fila);
                $fila_num++;
                continue;
            }
            if (count($fila) !== count($cabeceras)) {
                $errores[] = "Fila $fila_num: numero de columnas incorrecto";
                $fila_num++;
                continue;
            }
            $datos = array_combine($cabeceras, array_map("trim", $fila));
            if (empty($datos["identificacion"])) $errores[] = "Fila $fila_num: identificacion requerida";
            if (empty($datos["nombre_emp"]))     $errores[] = "Fila $fila_num: nombre_emp requerido";
            if (empty($datos["apellido_emp"]))   $errores[] = "Fila $fila_num: apellido_emp requerido";
            if (empty($datos["id_depto"]))       $errores[] = "Fila $fila_num: id_depto requerido";
            $datos["_existe"] = Empleado::where("identificacion", $datos["identificacion"])->exists();
            $filas[] = $datos;
            $fila_num++;
        }

        fclose($handle);
        return response()->json([
            "filas"   => $filas,
            "errores" => $errores,
            "total"   => count($filas),
        ]);
    }

    // Importar empleados
    public function importar(Request $request)
    {
        $request->validate([
            "archivo" => "required|file|mimes:csv,txt|max:5120",
        ]);

        $importados   = 0;
        $actualizados = 0;
        $errores      = [];
        $archivo      = $request->file("archivo");
        $handle       = fopen($archivo->getPathname(), "r");
        $cabeceras    = null;
        $fila_num     = 0;

        DB::beginTransaction();
        try {
            while (($fila = fgetcsv($handle, 1000, ",")) !== false) {
                if ($fila_num === 0) {
                    $cabeceras = array_map("trim", $fila);
                    $fila_num++;
                    continue;
                }
                if (count($fila) !== count($cabeceras)) {
                    $errores[] = "Fila $fila_num: numero de columnas incorrecto";
                    $fila_num++;
                    continue;
                }
                $datos = array_combine($cabeceras, array_map("trim", $fila));
                try {
                    $existe = Empleado::where("identificacion", $datos["identificacion"])->first();
                    if ($existe) {
                        $existe->update([
                            "nombre_emp"     => strtoupper($datos["nombre_emp"]),
                            "apellido_emp"   => strtoupper($datos["apellido_emp"]),
                            "id_depto"       => (int)$datos["id_depto"],
                            "estado"         => strtoupper($datos["estado"] ?? "ACTIVO"),
                            "cargo_empleado" => $datos["cargo_empleado"] ?? null,
                            "telefono"       => $datos["telefono"] ?? null,
                            "tipo_contrato"      => $datos["tipo_contrato"] ?? null,
                            "sueldo"         => !empty($datos["sueldo"]) ? (float)$datos["sueldo"] : null,
                            "nivel"          => !empty($datos["nivel"]) ? (int)$datos["nivel"] : null,
                            "calle_y_numero" => $datos["calle_y_numero"] ?? null,
                            "fecha_ingreso"  => !empty($datos["fecha_ingreso"]) ? $datos["fecha_ingreso"] : null,
                        ]);
                        if (!empty($datos["email"])) {
                            EmpleadoMail::where("id_emp", $existe->id_emp)->update(["estado" => "INACTIVO"]);
                            EmpleadoMail::create(["id_emp" => $existe->id_emp, "mail" => $datos["email"], "estado" => "ACTIVO"]);
                        }
                        $actualizados++;
                    } else {
                        $ultimo = Empleado::orderByRaw("id_emp DESC")->value("id_emp");
                        $numero = $ultimo ? ((int)$ultimo) + 1 : 1;
                        $id_emp = str_pad($numero, 5, "0", STR_PAD_LEFT);
                        $emp = Empleado::create([
                            "id_emp"         => $id_emp,
			    "identificacion" => str_pad($datos["identificacion"], 10, "0", STR_PAD_LEFT),
                            "nombre_emp"     => strtoupper($datos["nombre_emp"]),
                            "apellido_emp"   => strtoupper($datos["apellido_emp"]),
                            "id_depto"       => (int)$datos["id_depto"],
                            "estado"         => strtoupper($datos["estado"] ?? "ACTIVO"),
                            "cargo_empleado" => $datos["cargo_empleado"] ?? null,
                            "telefono"       => $datos["telefono"] ?? null,
                            "tipo_contrato"      => $datos["tipo_contrato"] ?? null,
                            "sueldo"         => !empty($datos["sueldo"]) ? (float)$datos["sueldo"] : null,
                            "nivel"          => !empty($datos["nivel"]) ? (int)$datos["nivel"] : null,
                            "calle_y_numero" => $datos["calle_y_numero"] ?? null,
                            "fecha_ingreso"  => !empty($datos["fecha_ingreso"]) ? $datos["fecha_ingreso"] : null,
                            "password"       => bcrypt($datos["identificacion"]),
                        ]);
                        if (!empty($datos["email"])) {
                            EmpleadoMail::create(["id_emp" => $emp->id_emp, "mail" => $datos["email"], "estado" => "ACTIVO"]);
                        }
                        $importados++;
                    }
                } catch (\Exception $e) {
                    $errores[] = "Fila $fila_num ($datos[identificacion]): " . $e->getMessage();
                }
                $fila_num++;
            }
            fclose($handle);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(["message" => "Error: " . $e->getMessage()], 500);
        }

        return response()->json([
            "message"      => "Importacion completada",
            "importados"   => $importados,
            "actualizados" => $actualizados,
            "errores"      => $errores,
            "total"        => $importados + $actualizados,
        ]);
    }
}
