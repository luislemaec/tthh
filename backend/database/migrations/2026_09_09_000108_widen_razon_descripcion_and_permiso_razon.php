<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Definición real de dbo.view_permisos_justificaciones (schema legado, sin migración propia
    // que la haya creado) — capturada con pg_get_viewdef() al toparse con el bloqueo de Postgres
    // al intentar el ALTER COLUMN (no se puede cambiar el tipo de una columna mientras una vista
    // dependa de ella). Se suelta, se ensanchan las columnas, y se recrea idéntica.
    private function viewSql(): string
    {
        return <<<'SQL'
            CREATE VIEW dbo.view_permisos_justificaciones AS
            SELECT to_char(p.fecha_desde, 'YYYY-MM-DD'::text) AS fecha_inicial,
                to_char(p.fecha_hasta, 'YYYY-MM-DD'::text) AS fecha_final,
                r.descripcion AS razon,
                d.nombre_depto,
                e.nombre_emp,
                e.apellido_emp,
                to_char(p.fecha_hora, 'YYYY-MM-DD'::text) AS fecha,
                p.id_emp,
                to_char(p.hora_desde, 'HH24:MI:SS'::text) AS hora_desde,
                to_char(p.hora_hasta, 'HH24:MI:SS'::text) AS hora_hasta,
                p.usuario,
                p.secuencial,
                p.observaciones,
                p.concepto,
                p.cargo,
                p.sec_permiso,
                r.tipo_razon,
                e.ubicacion,
                r.descontable,
                p.todo_dia,
                p.fecha_desde,
                p.fecha_hasta,
                COALESCE(p.estado_permiso, 'APROBADO'::character varying) AS estado_permiso,
                p.terminal
               FROM dbo.d2_permiso p
                 JOIN dbo.d2_razon r ON p.sec_permiso = r.secuencial
                 JOIN dbo.ad_empleado e ON p.id_emp::text = e.id_emp::text
                 JOIN dbo.ad_departamento d ON e.id_depto = d.id_depto
              WHERE p.observaciones::text <> 'MIGRACION'::text AND p.terminal::text <> '0.0.0.0'::text
            SQL;
    }

    public function up(): void
    {
        // dbo.d2_razon.descripcion venía en VARCHAR(30) (schema legado, sin migración propia) —
        // validado igual en RazonController (max:30). TH está cargando plantillas de razones con
        // nombres más largos (ej. "CALAMIDAD DOMESTICA JUSTIFICADA", 32 caracteres) y quedaban
        // truncadas (una fila real ya guardada como "CALAMIDAD DOMESTICA JUSTIFICAD", sin la A
        // final). Se amplía a VARCHAR(100).
        //
        // dbo.view_permisos_justificaciones depende de d2_razon.descripcion (r.descripcion AS
        // razon) — Postgres no permite ALTER COLUMN TYPE mientras una vista dependa de la
        // columna, hay que soltarla y recrearla.
        DB::statement('DROP VIEW IF EXISTS dbo.view_permisos_justificaciones');

        DB::statement('ALTER TABLE dbo.d2_razon ALTER COLUMN descripcion TYPE VARCHAR(100)');

        // dbo.d2_permiso.razon guarda una copia de razon.descripcion al momento de crear el
        // permiso (PermisosController::store(), trim($razon->descripcion)) — si esta columna se
        // queda más angosta que la de origen, la copia se trunca igual aunque el catálogo ya
        // permita el texto largo. Se ensancha en la misma medida.
        DB::statement('ALTER TABLE dbo.d2_permiso ALTER COLUMN razon TYPE VARCHAR(100)');

        DB::statement($this->viewSql());
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS dbo.view_permisos_justificaciones');
        DB::statement('ALTER TABLE dbo.d2_permiso ALTER COLUMN razon TYPE VARCHAR(30)');
        DB::statement('ALTER TABLE dbo.d2_razon ALTER COLUMN descripcion TYPE VARCHAR(30)');
        DB::statement($this->viewSql());
    }
};
