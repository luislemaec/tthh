<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Definiciones reales de las vistas legadas (schema dbo, sin migración propia que las haya
    // creado) que dependen de las columnas ensanchadas en esta migración — capturadas con
    // pg_get_viewdef() en el servidor de pruebas, una por una, cada vez que Postgres bloqueó el
    // ALTER COLUMN con "cannot alter type of a column used by a view or rule". Se sueltan las
    // dos antes de los ALTER y se recrean idénticas después.
    private function viewPermisosJustificacionesSql(): string
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

    private function viewVacacionesSolicitadasSql(): string
    {
        return <<<'SQL'
            CREATE VIEW dbo.view_vacaciones_solicitadas AS
            SELECT p.razon,
                d.nombre_depto,
                e.id_emp,
                e.apellido_emp,
                e.nombre_emp,
                date_part('year'::text, p.fecha_desde)::integer AS anio_desde,
                date_part('year'::text, p.fecha_hasta)::integer AS anio_hasta,
                date_part('month'::text, p.fecha_desde)::integer AS mes_desde,
                date_part('month'::text, p.fecha_hasta)::integer AS mes_hasta,
                COALESCE(p.estado_permiso, 'APROBADO'::character varying) AS estado,
                sum(
                    CASE
                        WHEN p.razon::text = 'VACACIONES'::text THEN p.fecha_hasta::date - p.fecha_desde::date + 1
                        ELSE p.fecha_hasta::date - p.fecha_desde::date + 1 - COALESCE(p.disminuir_dias, 0)
                    END) AS dias,
                p.terminal
               FROM dbo.d2_permiso p
                 JOIN dbo.ad_empleado e ON p.id_emp::text = e.id_emp::text
                 JOIN dbo.ad_departamento d ON e.id_depto = d.id_depto
              WHERE p.estado_permiso::text <> 'NEGADO'::text AND p.observaciones::text <> 'MIGRACION'::text AND p.terminal::text <> '0.0.0.0'::text
              GROUP BY p.razon, d.nombre_depto, e.id_emp, e.apellido_emp, e.nombre_emp, (date_part('year'::text, p.fecha_desde)), (date_part('year'::text, p.fecha_hasta)), (date_part('month'::text, p.fecha_desde)), (date_part('month'::text, p.fecha_hasta)), p.estado_permiso, p.terminal
            SQL;
    }

    public function up(): void
    {
        // dbo.d2_razon.descripcion venía en VARCHAR(30) (schema legado, sin migración propia) —
        // validado igual en RazonController (max:30). TH está cargando plantillas de razones con
        // nombres más largos (ej. "CALAMIDAD DOMESTICA JUSTIFICADA", 32 caracteres) y quedaban
        // truncadas (una fila real ya guardada como "CALAMIDAD DOMESTICA JUSTIFICAD", sin la A
        // final). Se amplía a VARCHAR(100), junto con d2_permiso.razon (la copia que se guarda
        // al crear un permiso — si solo se ensancha el catálogo y no esta columna, la copia se
        // sigue truncando igual).
        //
        // Ambas vistas dependen de las columnas que se ensanchan aquí — view_permisos_
        // justificaciones de d2_razon.descripcion (r.descripcion AS razon), view_vacaciones_
        // solicitadas de d2_permiso.razon directamente — y Postgres no permite ALTER COLUMN TYPE
        // mientras una vista dependa de la columna. Se sueltan las dos antes de los ALTER.
        DB::statement('DROP VIEW IF EXISTS dbo.view_permisos_justificaciones');
        DB::statement('DROP VIEW IF EXISTS dbo.view_vacaciones_solicitadas');

        DB::statement('ALTER TABLE dbo.d2_razon ALTER COLUMN descripcion TYPE VARCHAR(100)');
        DB::statement('ALTER TABLE dbo.d2_permiso ALTER COLUMN razon TYPE VARCHAR(100)');

        DB::statement($this->viewPermisosJustificacionesSql());
        DB::statement($this->viewVacacionesSolicitadasSql());
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS dbo.view_permisos_justificaciones');
        DB::statement('DROP VIEW IF EXISTS dbo.view_vacaciones_solicitadas');

        DB::statement('ALTER TABLE dbo.d2_permiso ALTER COLUMN razon TYPE VARCHAR(30)');
        DB::statement('ALTER TABLE dbo.d2_razon ALTER COLUMN descripcion TYPE VARCHAR(30)');

        DB::statement($this->viewPermisosJustificacionesSql());
        DB::statement($this->viewVacacionesSolicitadasSql());
    }
};
