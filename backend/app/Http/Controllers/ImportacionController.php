<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoMail;
use App\Models\CabeceraVacacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportacionController extends Controller
{
    private const COLUMNAS = [
        'identificacion', 'nombre_emp', 'apellido_emp', 'id_depto',
        'estado', 'estado_puesto', 'tipo_contrato', 'sueldo', 'nivel',
        'cargo_empleado', 'grupo_ocupacional', 'proceso_institucional',
        'modalidad_laboral', 'modalidad_marcacion',
        'partida_individual', 'partida_presupuestaria',
        'fecha_ingreso', 'fecha_salida',
        'acumula_decimo_tercero', 'acumula_decimo_cuarto', 'acumula_fondos_reserva',
        'puede_solicitar_vehiculo',
        'telefono', 'calle_y_numero', 'email',
    ];

    private const EJEMPLO = [
        '1234567890', 'JUAN CARLOS', 'PEREZ GARCIA', '16',
        'ACTIVO', 'OCUPADO', 'LOSEP', '1500.00', '1',
        'ANALISTA', 'GESTIÓN INTERNA', 'TALENTO HUMANO',
        'SERVIDOR PÚBLICO', 'PRESENCIAL',
        'PI-001', 'PP-001',
        '2020-01-15', '',
        '0', '0', '0',
        '0',
        '0991234567', 'Av. Amazonas 123', 'juan@correo.com',
    ];

    // Descargar plantilla CSV
    public function plantilla()
    {
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=plantilla_empleados.csv',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, self::COLUMNAS);
            fputcsv($file, self::EJEMPLO);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Vista previa del CSV
    public function preview(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $filas    = [];
        $errores  = [];
        $handle   = fopen($request->file('archivo')->getPathname(), 'r');
        $cabeceras = null;
        $fila_num  = 0;

        while (($fila = fgetcsv($handle, 2000, ',')) !== false) {
            if ($fila_num === 0) {
                $cabeceras = array_map('trim', $fila);
                $fila_num++;
                continue;
            }
            if (count($fila) !== count($cabeceras)) {
                $errores[] = "Fila $fila_num: número de columnas incorrecto";
                $fila_num++;
                continue;
            }
            $datos = array_combine($cabeceras, array_map('trim', $fila));
            if (empty($datos['identificacion'])) $errores[] = "Fila $fila_num: identificacion requerida";
            if (empty($datos['nombre_emp']))      $errores[] = "Fila $fila_num: nombre_emp requerido";
            if (empty($datos['apellido_emp']))    $errores[] = "Fila $fila_num: apellido_emp requerido";
            if (empty($datos['id_depto']))        $errores[] = "Fila $fila_num: id_depto requerido";
            $datos['_existe'] = Empleado::where('identificacion', $datos['identificacion'])->exists();
            $filas[] = $datos;
            $fila_num++;
        }

        fclose($handle);
        return response()->json([
            'filas'   => $filas,
            'errores' => $errores,
            'total'   => count($filas),
        ]);
    }

    // Importar empleados
    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $importados   = 0;
        $actualizados = 0;
        $errores      = [];
        $handle       = fopen($request->file('archivo')->getPathname(), 'r');
        $cabeceras    = null;
        $fila_num     = 0;

        DB::beginTransaction();
        try {
            while (($fila = fgetcsv($handle, 2000, ',')) !== false) {
                if ($fila_num === 0) {
                    $cabeceras = array_map('trim', $fila);
                    $fila_num++;
                    continue;
                }
                if (count($fila) !== count($cabeceras)) {
                    $errores[] = "Fila $fila_num: número de columnas incorrecto";
                    $fila_num++;
                    continue;
                }
                $d = array_combine($cabeceras, array_map('trim', $fila));
                try {
                    $campos = [
                        'nombre_emp'             => strtoupper($d['nombre_emp']),
                        'apellido_emp'           => strtoupper($d['apellido_emp']),
                        'id_depto'               => (int)$d['id_depto'],
                        'estado'                 => strtoupper($d['estado'] ?? 'ACTIVO'),
                        'estado_puesto'          => strtoupper($d['estado_puesto'] ?? 'OCUPADO'),
                        'tipo_contrato'          => $d['tipo_contrato'] ?? null,
                        'sueldo'                 => !empty($d['sueldo']) ? (float)$d['sueldo'] : null,
                        'nivel'                  => !empty($d['nivel']) ? (int)$d['nivel'] : null,
                        'cargo_empleado'         => $d['cargo_empleado'] ?? null,
                        'grupo_ocupacional'      => $d['grupo_ocupacional'] ?? null,
                        'proceso_institucional'  => $d['proceso_institucional'] ?? null,
                        'modalidad_laboral'      => $d['modalidad_laboral'] ?? null,
                        'modalidad_marcacion'    => strtoupper($d['modalidad_marcacion'] ?? 'PRESENCIAL'),
                        'partida_individual'     => $d['partida_individual'] ?? null,
                        'partida_presupuestaria' => $d['partida_presupuestaria'] ?? null,
                        'fecha_ingreso'          => !empty($d['fecha_ingreso']) ? $d['fecha_ingreso'] : null,
                        'fecha_salida'           => !empty($d['fecha_salida']) ? $d['fecha_salida'] : null,
                        'acumula_decimo_tercero' => (bool)($d['acumula_decimo_tercero'] ?? false),
                        'acumula_decimo_cuarto'  => (bool)($d['acumula_decimo_cuarto'] ?? false),
                        'acumula_fondos_reserva' => (int)($d['acumula_fondos_reserva'] ?? 0),
                        'puede_solicitar_vehiculo' => (bool)($d['puede_solicitar_vehiculo'] ?? false),
                        'telefono'               => $d['telefono'] ?? null,
                        'calle_y_numero'         => $d['calle_y_numero'] ?? null,
                    ];

                    $existe = Empleado::where('identificacion', $d['identificacion'])->first();
                    if ($existe) {
                        $existe->update($campos);
                        if (!empty($d['email'])) {
                            EmpleadoMail::where('id_emp', $existe->id_emp)->update(['estado' => 'INACTIVO']);
                            EmpleadoMail::create(['id_emp' => $existe->id_emp, 'mail' => $d['email'], 'estado' => 'ACTIVO']);
                        }
                        $actualizados++;
                    } else {
                        $ultimo = Empleado::orderByRaw('id_emp DESC')->value('id_emp');
                        $numero = $ultimo ? ((int)$ultimo) + 1 : 1;
                        $id_emp = str_pad($numero, 5, '0', STR_PAD_LEFT);
                        $emp = Empleado::create(array_merge($campos, [
                            'id_emp'         => $id_emp,
                            'identificacion' => str_pad($d['identificacion'], 10, '0', STR_PAD_LEFT),
                            'password'       => bcrypt($d['identificacion']),
                        ]));
                        CabeceraVacacion::firstOrCreate(
                            ['id_emp' => $emp->id_emp],
                            ['dias_adicionales' => 0, 'total_dias_tomados' => 0, 'total_tomados' => 0, 'dias_x_tomar_normal' => 0]
                        );
                        if (!empty($d['email'])) {
                            EmpleadoMail::create(['id_emp' => $emp->id_emp, 'mail' => $d['email'], 'estado' => 'ACTIVO']);
                        }
                        $importados++;
                    }
                } catch (\Exception $e) {
                    $errores[] = "Fila $fila_num ({$d['identificacion']}): " . $e->getMessage();
                }
                $fila_num++;
            }
            fclose($handle);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message'      => 'Importación completada',
            'importados'   => $importados,
            'actualizados' => $actualizados,
            'errores'      => $errores,
            'total'        => $importados + $actualizados,
        ]);
    }
}
