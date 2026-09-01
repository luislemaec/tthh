<?php
namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoHijo;
use App\Models\Departamento;
use App\Models\EmpleadoMail;
use App\Models\CabeceraVacacion;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class EmpleadoController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];

    // GET /api/empleados
    public function index(Request $request)
    {
        $query = Empleado::with(["departamento", "emails"])->where("id_depto", "!=", 999);

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

        if ($request->filled("tipo_contrato")) {
            $query->where("tipo_contrato", $request->tipo_contrato);
        }

        if ($request->filled("modalidad_laboral")) {
            $query->where("modalidad_laboral", $request->modalidad_laboral);
        }

        if ($request->filled("es_comisionado_entrante")) {
            $query->where("es_comisionado_entrante", $request->es_comisionado_entrante === '1');
        }

        $perPage = $request->get("per_page", 10);
        $data    = $query->orderBy("apellido_emp")->orderBy("nombre_emp")
                         ->paginate($perPage);

        return response()->json($data);
    }

    // GET /api/empleados/{id}
    public function show($id)
    {
        $emp  = Empleado::with(["departamento", "emails", "hijos"])->findOrFail($id);
        $data = $emp->toArray();
        $data['foto_url'] = $emp->foto_url;
        // Calcular años y guardería para cada hijo
        $hoy  = now()->toDateString();
        $data['hijos'] = collect($emp->hijos)->map(function ($h) use ($hoy) {
            $fn   = $h->fecha_nacimiento;
            $anos = $fn ? (int)floor((strtotime($hoy) - strtotime((string)$fn)) / (365.25 * 86400)) : null;
            return [
                'id'              => $h->id,
                'nombre'          => $h->nombre,
                'fecha_nacimiento'=> $h->fecha_nacimiento,
                'anos'            => $anos,
                'guarderia'       => $anos !== null && $anos < 5,
            ];
        })->values();
        return response()->json($data);
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
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            "identificacion"         => "required|string|max:15|unique:dbo.ad_empleado,identificacion",
            "nombre_emp"             => "required|string|max:240",
            "apellido_emp"           => "required|string|max:240",
            "id_depto"               => "required|integer",
            "fecha_ingreso"          => "nullable|date",
            "estado"                 => "nullable|string|max:10",
            "email"                  => "nullable|email",
            // Datos del puesto — obligatorios
            "cargo_empleado"         => "required|string|max:200",
            "grupo_ocupacional"      => "required|string|max:100",
            "nivel"                  => "required|integer",
            "sueldo"                 => "required|numeric|min:0",
            "partida_presupuestaria" => "required|string|max:100",
            "partida_individual"     => "required|integer|min:1",
            "proceso_institucional"  => "required|string|max:30",
            "modalidad_laboral"      => "required|string|max:50",
            "programa"               => "nullable|string|max:4",
            "actividad"              => "nullable|string|max:6",
            "sexo"                          => "nullable|string|max:10",
            "tipo_sangre"                   => "nullable|string|max:5",
            "num_sercop"                    => "nullable|string|max:50",
            "fecha_vence_sercop"            => "nullable|date",
            "grupo_vulnerable_id"           => "nullable|integer",
            "grupo_prioritario_id"          => "nullable|integer",
            "tiene_discapacidad"            => "nullable|boolean",
            "tipo_discapacidad_id"          => "nullable|integer",
            "porcentaje_discapacidad"       => "nullable|integer|min:0|max:100",
            "tiene_enfermedad_catastrofica" => "nullable|boolean",
            "enfermedad_catastrofica_id"    => "nullable|integer",
            "tiene_persona_sustituta"       => "nullable|boolean",
            "sustituta_fecha_caducidad"     => "nullable|date",
            "num_hijos_mayores"             => "nullable|integer|min:0",
        ]);

        $usuario = auth()->user()->id_emp ?? null;

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
            "cargo_empleado"   => $request->cargo_empleado,
            "telefono"         => $request->telefono,
            "extension"        => $request->extension        ?? null,
            "calle_y_numero"   => $request->calle_y_numero,
            "modalidad_laboral" => $request->modalidad_laboral,
            "id_jornada"        => $request->id_jornada,
            "programa"          => $request->filled('programa')    ? strtoupper($request->programa)    : null,
            "actividad"         => $request->filled('actividad')   ? strtoupper($request->actividad)   : null,
            "sexo"              => $request->filled('sexo')        ? strtoupper($request->sexo)        : null,
            "tipo_sangre"       => $request->filled('tipo_sangre') ? strtoupper($request->tipo_sangre) : null,
            "num_sercop"                    => $request->num_sercop         ?? null,
            "fecha_vence_sercop"            => $request->fecha_vence_sercop ?? null,
            "grupo_vulnerable_id"           => $request->grupo_vulnerable_id           ?? null,
            "grupo_prioritario_id"          => $request->grupo_prioritario_id          ?? null,
            "tiene_discapacidad"            => $request->boolean('tiene_discapacidad', false),
            "tipo_discapacidad_id"          => $request->tipo_discapacidad_id          ?? null,
            "porcentaje_discapacidad"       => $request->porcentaje_discapacidad       ?? null,
            "tiene_enfermedad_catastrofica" => $request->boolean('tiene_enfermedad_catastrofica', false),
            "enfermedad_catastrofica_id"    => $request->enfermedad_catastrofica_id    ?? null,
            "tiene_persona_sustituta"       => $request->boolean('tiene_persona_sustituta', false),
            "num_hijos_mayores"             => $request->num_hijos_mayores             ?? 0,
            "banco"                         => $request->filled('banco')         ? strtoupper($request->banco)       : null,
            "tipo_cuenta"                   => $request->filled('tipo_cuenta')   ? strtoupper($request->tipo_cuenta) : null,
            "numero_cuenta"                 => $request->filled('numero_cuenta') ? $request->numero_cuenta           : null,
            "created_at"       => now(),
            "created_by"       => $usuario,
            "updated_at"       => now(),
            "updated_by"       => $usuario,
        ]);
	$emp->password = bcrypt($request->identificacion);
	$emp->save();

        // Crear registro de vacaciones con saldo inicial 0
        CabeceraVacacion::firstOrCreate(
            ["id_emp" => $emp->id_emp],
            [
                "dias_adicionales"       => 0,
                "fecha_proceso"          => $emp->fecha_ingreso ?? now()->toDateString(),
                "total_dias_tomados"     => 0,
                "total_fin_semana"       => 0,
                "total_tomados"          => 0,
                "dias_x_tomar_normal"    => 0,
                "dias_x_tomar_fin_semana"=> 0,
                "dias_totales"           => 0,
                "venta_normal"           => 0,
                "venta_adicional"        => 0,
            ]
        );

        // Guardar email si se proporcionó
        if ($request->filled("email")) {
            EmpleadoMail::create([
                "id_emp" => $emp->id_emp,
                "mail"   => $request->email,
                "estado" => "ACTIVO",
            ]);
        }

        // Marcar como OCUPADO al titular inactivo/disponible que tenía esta partida
        if ($request->filled("partida_individual")) {
            Empleado::where("estado", "INACTIVO")
                ->where("estado_puesto", "DISPONIBLE")
                ->where("partida_individual", $request->partida_individual)
                ->where("id_emp", "!=", $emp->id_emp)
                ->update(["estado_puesto" => "OCUPADO"]);
        }

        AuditoriaService::log('dbo.ad_empleado', $emp->id_emp, 'CREAR',
            null,
            ['identificacion' => $emp->identificacion, 'nombre' => $emp->apellido_emp . ' ' . $emp->nombre_emp, 'id_depto' => $emp->id_depto, 'sueldo' => $emp->sueldo],
            $request, 'Creación de empleado');

        return response()->json($emp->load(["departamento", "emails"]), 201);
    }

    // PUT /api/empleados/{id}
    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $emp = Empleado::findOrFail($id);

        if ($emp->es_externo) {
            return response()->json(['message' => 'Los funcionarios externos se gestionan desde el módulo de Comisiones.'], 422);
        }
        $anterior = [
            'sueldo'               => $emp->sueldo,
            'estado'               => $emp->estado,
            'id_depto'             => $emp->id_depto,
            'cargo_empleado'       => $emp->cargo_empleado,
            'tipo_contrato'        => $emp->tipo_contrato,
            'modalidad_laboral'    => $emp->modalidad_laboral,
            'partida_individual'   => $emp->partida_individual,
            'programa'             => $emp->programa,
            'actividad'            => $emp->actividad,
            'modalidad_marcacion'  => $emp->modalidad_marcacion,
            'motivo_salida'        => $emp->motivo_salida,
            'motivo_reactivacion'  => $emp->motivo_reactivacion,
            'institucion_comision'     => $emp->institucion_comision,
            'es_comisionado_entrante'  => (bool) $emp->es_comisionado_entrante,
        ];

        $request->validate([
            "identificacion"         => "nullable|string|max:15",
            "nombre_emp"             => "nullable|string|max:240",
            "apellido_emp"           => "nullable|string|max:240",
            "id_depto"               => "nullable|integer",
            "fecha_ingreso"          => "nullable|date",
            "estado"                 => "nullable|string|max:10",
            "email"                  => "nullable|email",
            // Datos del puesto — obligatorios
            "cargo_empleado"         => "required|string|max:200",
            "grupo_ocupacional"      => "required|string|max:100",
            "nivel"                  => "required|integer",
            "sueldo"                 => "required|numeric|min:0",
            "partida_presupuestaria" => "required|string|max:100",
            "partida_individual"     => "required|integer|min:1",
            "proceso_institucional"  => "required|string|max:30",
            "modalidad_laboral"      => "required|string|max:50",
            "programa"               => "nullable|string|max:4",
            "actividad"              => "nullable|string|max:6",
            "sexo"                   => "nullable|string|max:10",
            "tipo_sangre"            => "nullable|string|max:5",
            "num_sercop"                    => "nullable|string|max:50",
            "fecha_vence_sercop"            => "nullable|date",
            "grupo_vulnerable_id"           => "nullable|integer",
            "grupo_prioritario_id"          => "nullable|integer",
            "tiene_discapacidad"            => "nullable|boolean",
            "tipo_discapacidad_id"          => "nullable|integer",
            "porcentaje_discapacidad"       => "nullable|integer|min:0|max:100",
            "tiene_enfermedad_catastrofica" => "nullable|boolean",
            "enfermedad_catastrofica_id"    => "nullable|integer",
            "tiene_persona_sustituta"       => "nullable|boolean",
            "sustituta_fecha_caducidad"     => "nullable|date",
            "num_hijos_mayores"             => "nullable|integer|min:0",
        ]);

        $emp->update([
            "identificacion"        => $request->identificacion        ?? $emp->identificacion,
            "nombre_emp"            => $request->filled("nombre_emp")    ? strtoupper($request->nombre_emp)   : $emp->nombre_emp,
            "apellido_emp"          => $request->filled("apellido_emp")  ? strtoupper($request->apellido_emp) : $emp->apellido_emp,
            "id_depto"              => $request->id_depto               ?? $emp->id_depto,
            "estado"                => $request->filled("estado")        ? strtoupper($request->estado)       : $emp->estado,
            "tipo_contrato"         => $request->tipo_contrato           ?? $emp->tipo_contrato,
            "jornada_id"            => $request->jornada_id              ?? $emp->jornada_id,
            "fecha_ingreso"         => $request->fecha_ingreso           ?? $emp->fecha_ingreso,
            "fecha_salida"          => $request->fecha_salida            ?? $emp->fecha_salida,
            "sueldo"                => $request->sueldo                  ?? $emp->sueldo,
            "nivel"                 => $request->nivel                   ?? $emp->nivel,
            "ubicacion"             => $request->ubicacion               ?? $emp->ubicacion,
            "cargo_empleado"        => $request->cargo_empleado          ?? $emp->cargo_empleado,
            "telefono"              => $request->telefono                ?? $emp->telefono,
            "extension"             => $request->filled('extension')     ? $request->extension : $emp->extension,
            "calle_y_numero"        => $request->calle_y_numero          ?? $emp->calle_y_numero,
            "modalidad_laboral"     => $request->modalidad_laboral       ?? $emp->modalidad_laboral,
            "id_jornada"            => $request->id_jornada              ?? $emp->id_jornada,
            "partida_individual"    => $request->partida_individual      ?? $emp->partida_individual,
            "partida_presupuestaria"=> $request->partida_presupuestaria  ?? $emp->partida_presupuestaria,
            "estado_puesto"         => strtoupper($request->estado ?? $emp->estado) === 'INACTIVO'
                                        ? 'DISPONIBLE'
                                        : ($request->estado_puesto ?? $emp->estado_puesto),
            "grupo_ocupacional"     => $request->grupo_ocupacional       ?? $emp->grupo_ocupacional,
            "proceso_institucional" => $request->proceso_institucional   ?? $emp->proceso_institucional,
            "acumula_fondos_reserva"    => $request->acumula_fondos_reserva    ?? $emp->acumula_fondos_reserva,
            "acumula_decimo_tercero"    => $request->acumula_decimo_tercero    ?? $emp->acumula_decimo_tercero,
            "acumula_decimo_cuarto"     => $request->acumula_decimo_cuarto     ?? $emp->acumula_decimo_cuarto,
            "programa"                  => $request->filled('programa')  ? strtoupper($request->programa)  : ($emp->programa  ?? null),
            "actividad"                 => $request->filled('actividad') ? strtoupper($request->actividad) : ($emp->actividad ?? null),
            "modalidad_marcacion"       => $request->modalidad_marcacion       ?? $emp->modalidad_marcacion,
            "puede_solicitar_vehiculo"  => $request->boolean('puede_solicitar_vehiculo', $emp->puede_solicitar_vehiculo ?? false),
            "sexo"                      => $request->filled('sexo')        ? strtoupper($request->sexo)        : $emp->sexo,
            "tipo_sangre"               => $request->filled('tipo_sangre') ? strtoupper($request->tipo_sangre) : $emp->tipo_sangre,
            "num_sercop"                    => $request->num_sercop         ?? $emp->num_sercop,
            "fecha_vence_sercop"            => $request->fecha_vence_sercop  ?? $emp->fecha_vence_sercop,
            "grupo_vulnerable_id"           => $request->filled('grupo_vulnerable_id')  ? $request->grupo_vulnerable_id  : $emp->grupo_vulnerable_id,
            "grupo_prioritario_id"          => $request->filled('grupo_prioritario_id') ? $request->grupo_prioritario_id : $emp->grupo_prioritario_id,
            "tiene_discapacidad"            => $request->has('tiene_discapacidad')            ? $request->boolean('tiene_discapacidad')            : $emp->tiene_discapacidad,
            "tipo_discapacidad_id"          => $request->filled('tipo_discapacidad_id')       ? $request->tipo_discapacidad_id       : $emp->tipo_discapacidad_id,
            "porcentaje_discapacidad"       => $request->filled('porcentaje_discapacidad')    ? $request->porcentaje_discapacidad    : $emp->porcentaje_discapacidad,
            "tiene_enfermedad_catastrofica" => $request->has('tiene_enfermedad_catastrofica') ? $request->boolean('tiene_enfermedad_catastrofica') : $emp->tiene_enfermedad_catastrofica,
            "enfermedad_catastrofica_id"    => $request->filled('enfermedad_catastrofica_id') ? $request->enfermedad_catastrofica_id : $emp->enfermedad_catastrofica_id,
            "tiene_persona_sustituta"       => $request->has('tiene_persona_sustituta')       ? $request->boolean('tiene_persona_sustituta')       : $emp->tiene_persona_sustituta,
            "sustituta_fecha_caducidad"     => $request->sustituta_fecha_caducidad ?? $emp->sustituta_fecha_caducidad,
            "num_hijos_mayores"             => $request->filled('num_hijos_mayores') ? (int)$request->num_hijos_mayores : $emp->num_hijos_mayores,
            "motivo_salida"         => $request->has('motivo_salida')         ? ($request->motivo_salida         ?: null) : $emp->motivo_salida,
            "motivo_reactivacion"   => $request->has('motivo_reactivacion')   ? ($request->motivo_reactivacion   ?: null) : $emp->motivo_reactivacion,
            "institucion_comision"       => $request->has('institucion_comision')       ? ($request->institucion_comision       ?: null) : $emp->institucion_comision,
            "es_comisionado_entrante"    => $request->has('es_comisionado_entrante')    ? $request->boolean('es_comisionado_entrante') : $emp->es_comisionado_entrante,
            "banco"                 => $request->filled('banco')         ? strtoupper($request->banco)         : ($request->has('banco')         ? null : $emp->banco),
            "tipo_cuenta"           => $request->filled('tipo_cuenta')   ? strtoupper($request->tipo_cuenta)   : ($request->has('tipo_cuenta')   ? null : $emp->tipo_cuenta),
            "numero_cuenta"         => $request->filled('numero_cuenta') ? $request->numero_cuenta             : ($request->has('numero_cuenta') ? null : $emp->numero_cuenta),
            "updated_at"                => now(),
            "updated_by"                => auth()->user()->id_emp ?? null,
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

        // Marcar como OCUPADO al titular inactivo/disponible que tenía esta partida
        if ($request->filled("partida_individual")) {
            Empleado::where("estado", "INACTIVO")
                ->where("estado_puesto", "DISPONIBLE")
                ->where("partida_individual", $request->partida_individual)
                ->where("id_emp", "!=", $emp->id_emp)
                ->update(["estado_puesto" => "OCUPADO"]);
        }

        AuditoriaService::log('dbo.ad_empleado', $emp->id_emp, 'ACTUALIZAR',
            $anterior,
            [
                'sueldo'              => $emp->sueldo,
                'estado'              => $emp->estado,
                'id_depto'            => $emp->id_depto,
                'cargo_empleado'      => $emp->cargo_empleado,
                'tipo_contrato'       => $emp->tipo_contrato,
                'modalidad_laboral'   => $emp->modalidad_laboral,
                'partida_individual'  => $emp->partida_individual,
                'programa'            => $emp->programa,
                'actividad'           => $emp->actividad,
                'modalidad_marcacion' => $emp->modalidad_marcacion,
                'motivo_salida'           => $emp->motivo_salida,
                'motivo_reactivacion'     => $emp->motivo_reactivacion,
                'institucion_comision'    => $emp->institucion_comision,
                'es_comisionado_entrante' => $emp->es_comisionado_entrante,
            ],
            $request, 'Actualización de empleado: ' . trim($emp->apellido_emp . ' ' . $emp->nombre_emp));

        $emp->load(["departamento", "emails"]);
        $data = $emp->toArray();
        $data['foto_url'] = $emp->foto_url;
        return response()->json($data);
    }

    // DELETE /api/empleados/{id}
    public function destroy(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $emp = Empleado::findOrFail($id);
        $emp->update(["estado" => "INACTIVO"]);
        return response()->json(["message" => "Empleado desactivado correctamente."]);
    }

    // POST /api/empleados/{id}/reset-password — solo Admin o TH
    public function resetPassword(Request $request, $id)
    {
        $user = $request->user();
        $esAdminOTH = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $user->id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])
            ->exists();

        if (!$esAdminOTH) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        $emp = Empleado::findOrFail($id);
        $emp->password = bcrypt($emp->identificacion);
        $emp->save();

        return response()->json(['message' => "Contraseña reseteada a la cédula del empleado."]);
    }

    // POST /api/cambiar-password — empleado cambia su propia contraseña
    public function cambiarPassword(Request $request)
    {
        $request->validate([
            'password_actual' => 'required|string',
            'password_nuevo'  => 'required|string|min:6',
            'password_confirmar' => 'required|same:password_nuevo',
        ]);

        $emp = $request->user();

        if (!Hash::check($request->password_actual, $emp->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
        }

        $emp->password = bcrypt($request->password_nuevo);
        $emp->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    // POST /api/empleados/importar-distributivo — solo Admin o TH
    public function importarDistributivo(Request $request)
    {
        $user = $request->user();
        $esAdminOTH = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $user->id_emp)
            ->whereIn('r.descripcion', ['ADMINISTRADOR', 'TALENTO HUMANO'])
            ->exists();

        if (!$esAdminOTH) {
            return response()->json(['message' => 'Acceso no autorizado.'], 403);
        }

        $request->validate(['archivo' => 'required|file|mimes:csv,txt|max:2048']);

        $path = $request->file('archivo')->getRealPath();
        $handle = fopen($path, 'r');

        // Leer encabezado
        $header = fgetcsv($handle, 0, ',');
        if (!$header) {
            fclose($handle);
            return response()->json(['message' => 'Archivo CSV vacío o inválido.'], 422);
        }

        // Normalizar nombres de columna
        $header = array_map(fn($h) => strtoupper(trim(preg_replace('/\s+/', ' ', $h))), $header);

        $actualizados = 0;
        $noEncontrados = [];
        $errores = [];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) < 12) continue;

            $fila = array_combine(array_slice($header, 0, count($row)), $row);

            // La cédula puede venir sin ceros iniciales — rellenar a 10 dígitos
            $cedula = str_pad(trim($fila['IDENTIFICACION'] ?? ''), 10, '0', STR_PAD_LEFT);
            if (empty($cedula) || $cedula === '0000000000') continue;

            $emp = Empleado::where('identificacion', $cedula)->first();
            if (!$emp) {
                $noEncontrados[] = $cedula;
                continue;
            }

            try {
                // Columna 0: partida_individual (corta), columna 9: partida_presupuestaria (larga)
                // Hay dos columnas con el mismo nombre "PARTIDA INDIVIDUAL" en el CSV
                $partida_individual     = isset($row[0]) ? (int) trim($row[0]) : $emp->partida_individual;
                $partida_presupuestaria = isset($row[9]) ? trim($row[9])       : $emp->partida_presupuestaria;

                $acumulaFondos = isset($fila['ACUMULA FONDOS DE RESERVA'])
                    ? (int) trim($fila['ACUMULA FONDOS DE RESERVA'])
                    : $emp->acumula_fondos_reserva;

                $emp->update([
                    'nivel'                   => isset($fila['GRADO'])                  ? (int) trim($fila['GRADO'])                  : $emp->nivel,
                    'grupo_ocupacional'       => isset($fila['GRUPO OCUPACIONAL'])      ? trim($fila['GRUPO OCUPACIONAL'])            : $emp->grupo_ocupacional,
                    'proceso_institucional'   => isset($fila['PROCESO INSTITUCIONAL'])  ? trim($fila['PROCESO INSTITUCIONAL'])        : $emp->proceso_institucional,
                    'partida_individual'      => $partida_individual,
                    'partida_presupuestaria'  => $partida_presupuestaria,
                    'estado_puesto'           => isset($fila['ESTADO DEL PUESTO'])      ? trim($fila['ESTADO DEL PUESTO'])            : $emp->estado_puesto,
                    'acumula_fondos_reserva'  => $acumulaFondos,
                    'acumula_decimo_tercero'  => isset($fila['ACUMULA DÉCIMO TERCERO']) ? (strtoupper(trim($fila['ACUMULA DÉCIMO TERCERO'])) === 'SI') : $emp->acumula_decimo_tercero,
                    'acumula_decimo_cuarto'   => isset($fila['ACUMULA DÉCIMO CUARTO'])  ? (strtoupper(trim($fila['ACUMULA DÉCIMO CUARTO']))  === 'SI') : $emp->acumula_decimo_cuarto,
                ]);
                $actualizados++;
            } catch (\Exception $e) {
                $errores[] = $cedula . ': ' . $e->getMessage();
            }
        }

        fclose($handle);

        return response()->json([
            'message'        => "Importación completada.",
            'actualizados'   => $actualizados,
            'no_encontrados' => $noEncontrados,
            'errores'        => $errores,
        ]);
    }

    // GET /api/departamentos
    public function departamentos()
    {
        $deps = Departamento::orderBy("nombre_depto")->get(["id_depto", "nombre_depto"]);
        return response()->json($deps);
    }

    public function partidasVacantes()
    {
        $partidas = Empleado::where('estado', 'INACTIVO')
            ->where('estado_puesto', 'DISPONIBLE')
            ->whereNotNull('partida_individual')
            ->orderBy('partida_individual')
            ->get(['id_emp', 'nombre_emp', 'apellido_emp', 'partida_individual', 'partida_presupuestaria']);
        return response()->json($partidas);
    }

    // POST /api/empleados/{id}/foto
    public function subirFoto(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate(['foto' => 'required|image|max:2048']);

        $emp = Empleado::findOrFail($id);

        if ($emp->foto) {
            Storage::disk('public')->delete($emp->foto);
        }

        $path = $request->file('foto')->store('empleados', 'public');
        $emp->update(['foto' => $path]);

        return response()->json(['foto' => $path]);
    }

    // DELETE /api/empleados/{id}/foto
    public function eliminarFoto(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $emp = Empleado::findOrFail($id);

        if ($emp->foto) {
            Storage::disk('public')->delete($emp->foto);
            $emp->update(['foto' => null]);
        }

        return response()->json(['message' => 'Foto eliminada.']);
    }

    // GET /api/empleados/catalogos-sociales
    public function catalogosSociales()
    {
        return response()->json([
            'grupos_vulnerables'     => DB::table('dbo.ad_grupo_vulnerable')    ->where('activo', true)->orderBy('nombre')->get(['id','nombre']),
            'grupos_prioritarios'    => DB::table('dbo.ad_grupo_prioritario')   ->where('activo', true)->orderBy('nombre')->get(['id','nombre']),
            'tipos_discapacidad'     => DB::table('dbo.ad_tipo_discapacidad')   ->where('activo', true)->orderBy('nombre')->get(['id','nombre']),
            'enfermedades_catastroficas' => DB::table('dbo.ad_enfermedad_catastrofica')->where('activo', true)->orderBy('nombre')->get(['id','nombre']),
        ]);
    }

    // GET /api/empleados/{id}/hijos
    public function hijoIndex(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $hijos = EmpleadoHijo::where('id_emp', $id)->orderBy('fecha_nacimiento')->get();
        $hoy   = now()->toDateString();
        return response()->json($hijos->map(function ($h) use ($hoy) {
            $fn   = $h->fecha_nacimiento;
            $anos = $fn ? (int)floor((strtotime($hoy) - strtotime($fn)) / (365.25 * 86400)) : null;
            return [
                'id'              => $h->id,
                'nombre'          => $h->nombre,
                'fecha_nacimiento'=> $h->fecha_nacimiento,
                'anos'            => $anos,
                'guarderia'       => $anos !== null && $anos < 5,
            ];
        }));
    }

    // POST /api/empleados/{id}/hijos
    public function hijoStore(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            'fecha_nacimiento' => 'required|date|before_or_equal:today',
            'nombre'           => 'nullable|string|max:200',
        ]);
        Empleado::findOrFail($id);
        $hijo = EmpleadoHijo::create([
            'id_emp'           => $id,
            'nombre'           => $request->nombre,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'created_at'       => now(),
        ]);
        $anos = (int)floor((time() - strtotime($hijo->fecha_nacimiento)) / (365.25 * 86400));
        return response()->json([
            'id'              => $hijo->id,
            'nombre'          => $hijo->nombre,
            'fecha_nacimiento'=> $hijo->fecha_nacimiento,
            'anos'            => $anos,
            'guarderia'       => $anos < 5,
        ], 201);
    }

    // DELETE /api/empleados/{id}/hijos/{hijoId}
    public function hijoDestroy(Request $request, $id, $hijoId)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $hijo = EmpleadoHijo::where('id_emp', $id)->where('id', $hijoId)->firstOrFail();
        $hijo->delete();
        return response()->json(['message' => 'Hijo eliminado.']);
    }

    // ── Documento persona sustituta (Alfresco) ──────────────────────────────

    private string $alfrescoBase;
    private string $alfrescoUser;
    private string $alfrescoPass;
    private string $alfrescoSite;

    public function __construct()
    {
        $this->alfrescoBase = config('services.alfresco.base');
        $this->alfrescoUser = config('services.alfresco.user');
        $this->alfrescoPass = config('services.alfresco.pass');
        $this->alfrescoSite = config('services.alfresco.site');
    }

    private function getDocLibNodeId(): string
    {
        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/sites/{$this->alfrescoSite}/containers/documentLibrary");
        return $resp->json('entry.id');
    }

    // POST /api/empleados/{id}/sustituta-doc
    public function subirDocSustituta(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate([
            'documento'            => 'required|file|mimes:pdf|max:5120',
            'sustituta_fecha_caducidad' => 'nullable|date',
        ]);

        $emp      = Empleado::findOrFail($id);
        $archivo  = $request->file('documento');
        $cedula   = $emp->identificacion;
        $apellido = strtoupper(explode(' ', trim($emp->apellido_emp))[0]);
        $carpeta  = "empleados/{$cedula}_{$apellido}";
        $nombre   = "sustituta_{$cedula}_" . now()->format('Ymd_His') . '.pdf';

        $docLibId = $this->getDocLibNodeId();
        $upload   = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->attach('filedata', file_get_contents($archivo->getRealPath()), $nombre)
            ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
                'name'         => $nombre,
                'nodeType'     => 'cm:content',
                'relativePath' => $carpeta,
                'autoRename'   => true,
            ]);

        if (!$upload->successful()) {
            return response()->json(['message' => 'Error al subir el documento a Alfresco.'], 502);
        }

        $nodeId = $upload->json('entry.id');
        $emp->update([
            'sustituta_alfresco_id'    => $nodeId,
            'sustituta_nombre_archivo' => $nombre,
            'sustituta_fecha_caducidad'=> $request->sustituta_fecha_caducidad ?? null,
            'tiene_persona_sustituta'  => true,
        ]);

        return response()->json([
            'sustituta_alfresco_id'    => $nodeId,
            'sustituta_nombre_archivo' => $nombre,
            'sustituta_fecha_caducidad'=> $emp->sustituta_fecha_caducidad,
        ]);
    }

    // GET /api/empleados/{id}/sustituta-doc
    public function descargarDocSustituta(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $emp = Empleado::findOrFail($id);
        if (!$emp->sustituta_alfresco_id) {
            return response()->json(['message' => 'Sin documento de persona sustituta.'], 404);
        }
        $resp = Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
            ->get("{$this->alfrescoBase}/nodes/{$emp->sustituta_alfresco_id}/content");

        return response($resp->body(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $emp->sustituta_nombre_archivo . '"',
        ]);
    }

    // DELETE /api/empleados/{id}/sustituta-doc
    public function eliminarDocSustituta(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $emp = Empleado::findOrFail($id);
        if ($emp->sustituta_alfresco_id) {
            Http::withBasicAuth($this->alfrescoUser, $this->alfrescoPass)
                ->delete("{$this->alfrescoBase}/nodes/{$emp->sustituta_alfresco_id}");
        }
        $emp->update([
            'sustituta_alfresco_id'    => null,
            'sustituta_nombre_archivo' => null,
        ]);
        return response()->json(['message' => 'Documento eliminado.']);
    }

    // ── Períodos de teletrabajo ─────────────────────────────────────────────

    // GET /api/empleados/{id}/teletrabajo
    public function teletrabajoIndex(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $periodos = DB::table('dbo.ad_empleado_teletrabajo')
            ->where('id_emp', $id)
            ->orderByDesc('fecha_desde')
            ->get();
        return response()->json($periodos);
    }

    // POST /api/empleados/{id}/teletrabajo
    public function teletrabajoStore(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        Empleado::findOrFail($id);

        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ]);

        $periodo = DB::table('dbo.ad_empleado_teletrabajo')->insertGetId([
            'id_emp'      => $id,
            'fecha_desde' => $request->fecha_desde,
            'fecha_hasta' => $request->fecha_hasta,
            'created_by'  => $request->user()->id_emp,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(
            DB::table('dbo.ad_empleado_teletrabajo')->find($periodo),
            201
        );
    }

    // DELETE /api/empleados/{id}/teletrabajo/{periodoId}
    public function teletrabajoDestroy(Request $request, $id, $periodoId)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $deleted = DB::table('dbo.ad_empleado_teletrabajo')
            ->where('id', $periodoId)
            ->where('id_emp', $id)
            ->delete();

        if (!$deleted) abort(404);
        return response()->json(['message' => 'Período eliminado.']);
    }
}
