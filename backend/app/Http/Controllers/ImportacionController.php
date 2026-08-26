<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoMail;
use App\Models\CabeceraVacacion;
use Carbon\Carbon;
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
        'programa', 'actividad',
        'fecha_ingreso', 'fecha_salida',
        'motivo_salida', 'motivo_reactivacion', 'institucion_comision',
        'acumula_decimo_tercero', 'acumula_decimo_cuarto', 'acumula_fondos_reserva',
        'puede_solicitar_vehiculo',
        'sexo', 'tipo_sangre',
        'num_sercop', 'fecha_vence_sercop',
        'banco', 'tipo_cuenta', 'numero_cuenta',
        'telefono', 'extension', 'calle_y_numero', 'email',
        'grupo_vulnerable', 'grupo_prioritario',
        'tiene_discapacidad', 'tipo_discapacidad', 'porcentaje_discapacidad',
        'tiene_enfermedad_catastrofica', 'enfermedad_catastrofica',
        'tiene_persona_sustituta', 'sustituta_fecha_caducidad',
        'num_hijos_mayores',
    ];

    // Catálogos: nombre debe coincidir (sin distinguir mayúsculas) con dbo.ad_grupo_vulnerable /
    // ad_grupo_prioritario / ad_tipo_discapacidad / ad_enfermedad_catastrofica. Dejar vacío si no aplica.
    // Fechas (fecha_ingreso, fecha_salida, fecha_vence_sercop, sustituta_fecha_caducidad): DD/MM/AAAA,
    // AAAA-MM-DD o DD-MM-AAAA — cualquiera de los tres formatos se acepta.
    // Hijos menores individuales y el documento de persona sustituta NO se cargan por este CSV —
    // se gestionan desde la ficha del empleado (Tab 1).
    private const EJEMPLO = [
        '1234567890', 'JUAN CARLOS', 'PEREZ GARCIA', '16',
        'ACTIVO', 'OCUPADO', 'LOSEP', '1500.00', '1',
        'ANALISTA', 'GESTIÓN INTERNA', 'TALENTO HUMANO',
        'SERVIDOR PÚBLICO', 'PRESENCIAL',
        'PI-001', 'PP-001',
        '55', '001',
        '15/01/2020', '',
        '', '', '',
        '0', '0', '0',
        '0',
        'MASCULINO', 'O+',
        '', '',
        'BANCO PICHINCHA', 'AHORROS', '2200123456',
        '0991234567', '102', 'Av. Amazonas 123', 'juan@correo.com',
        '', '',
        'NO', '', '',
        'NO', '',
        'NO', '',
        '0',
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

        $mapaCatalogo = fn (string $tabla) => DB::table($tabla)->get(['id', 'nombre'])
            ->mapWithKeys(fn ($r) => [strtoupper(trim($r->nombre)) => $r->id])->toArray();

        $catGrupoVulnerable  = $mapaCatalogo('dbo.ad_grupo_vulnerable');
        $catGrupoPrioritario = $mapaCatalogo('dbo.ad_grupo_prioritario');
        $catTipoDiscapacidad = $mapaCatalogo('dbo.ad_tipo_discapacidad');
        $catEnfermedad       = $mapaCatalogo('dbo.ad_enfermedad_catastrofica');

        $buscarCatalogo = function (array $catalogo, ?string $nombre, string $etiqueta, int $filaNum, array &$errores): ?int {
            $nombre = trim((string) $nombre);
            if ($nombre === '') return null;
            $id = $catalogo[strtoupper($nombre)] ?? null;
            if ($id === null) {
                $errores[] = "Fila $filaNum: $etiqueta '$nombre' no encontrado en el catálogo, se dejó vacío";
            }
            return $id;
        };

        // Acepta DD/MM/AAAA (formato típico de Excel en Ecuador), AAAA-MM-DD y DD-MM-AAAA
        $parseFecha = function (?string $valor, string $campo): ?string {
            $valor = trim((string) $valor);
            if ($valor === '') return null;
            foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'] as $formato) {
                try {
                    $fecha = Carbon::createFromFormat($formato, $valor);
                    if ($fecha !== false) return $fecha->format('Y-m-d');
                } catch (\Exception $e) {
                    continue;
                }
            }
            throw new \Exception("$campo '$valor' no es una fecha válida (use DD/MM/AAAA)");
        };

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
                        'programa'               => !empty($d['programa'])  ? strtoupper($d['programa'])  : null,
                        'actividad'              => !empty($d['actividad']) ? strtoupper($d['actividad']) : null,
                        'fecha_ingreso'          => $parseFecha($d['fecha_ingreso'] ?? '', 'fecha_ingreso'),
                        'fecha_salida'           => $parseFecha($d['fecha_salida'] ?? '', 'fecha_salida'),
                        'motivo_salida'          => !empty($d['motivo_salida'])        ? $d['motivo_salida']        : null,
                        'motivo_reactivacion'    => !empty($d['motivo_reactivacion'])  ? $d['motivo_reactivacion']  : null,
                        'institucion_comision'   => !empty($d['institucion_comision']) ? $d['institucion_comision'] : null,
                        'acumula_decimo_tercero' => (bool)($d['acumula_decimo_tercero'] ?? false),
                        'acumula_decimo_cuarto'  => (bool)($d['acumula_decimo_cuarto'] ?? false),
                        'acumula_fondos_reserva' => (int)($d['acumula_fondos_reserva'] ?? 0),
                        'puede_solicitar_vehiculo' => (bool)($d['puede_solicitar_vehiculo'] ?? false),
                        'sexo'                   => !empty($d['sexo'])        ? strtoupper($d['sexo'])        : null,
                        'tipo_sangre'            => !empty($d['tipo_sangre']) ? strtoupper($d['tipo_sangre']) : null,
                        'num_sercop'             => !empty($d['num_sercop']) ? $d['num_sercop'] : null,
                        'fecha_vence_sercop'     => $parseFecha($d['fecha_vence_sercop'] ?? '', 'fecha_vence_sercop'),
                        'banco'                  => !empty($d['banco'])       ? strtoupper($d['banco'])       : null,
                        'tipo_cuenta'            => !empty($d['tipo_cuenta']) ? strtoupper($d['tipo_cuenta']) : null,
                        'numero_cuenta'          => !empty($d['numero_cuenta']) ? $d['numero_cuenta'] : null,
                        'telefono'               => $d['telefono'] ?? null,
                        'extension'              => !empty($d['extension']) ? $d['extension'] : null,
                        'calle_y_numero'         => $d['calle_y_numero'] ?? null,
                        'grupo_vulnerable_id'    => $buscarCatalogo($catGrupoVulnerable,  $d['grupo_vulnerable']  ?? null, 'grupo vulnerable',  $fila_num, $errores),
                        'grupo_prioritario_id'   => $buscarCatalogo($catGrupoPrioritario, $d['grupo_prioritario'] ?? null, 'grupo prioritario', $fila_num, $errores),
                        'tiene_discapacidad'     => strtoupper(trim($d['tiene_discapacidad'] ?? '')) === 'SI',
                        'tipo_discapacidad_id'   => $buscarCatalogo($catTipoDiscapacidad, $d['tipo_discapacidad'] ?? null, 'tipo de discapacidad', $fila_num, $errores),
                        'porcentaje_discapacidad' => !empty($d['porcentaje_discapacidad']) ? (float)$d['porcentaje_discapacidad'] : null,
                        'tiene_enfermedad_catastrofica' => strtoupper(trim($d['tiene_enfermedad_catastrofica'] ?? '')) === 'SI',
                        'enfermedad_catastrofica_id'    => $buscarCatalogo($catEnfermedad, $d['enfermedad_catastrofica'] ?? null, 'enfermedad catastrófica', $fila_num, $errores),
                        'tiene_persona_sustituta'   => strtoupper(trim($d['tiene_persona_sustituta'] ?? '')) === 'SI',
                        'sustituta_fecha_caducidad' => $parseFecha($d['sustituta_fecha_caducidad'] ?? '', 'sustituta_fecha_caducidad'),
                        'num_hijos_mayores'         => !empty($d['num_hijos_mayores']) ? (int)$d['num_hijos_mayores'] : 0,
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
