<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de tipos de equipo
        DB::statement("
            CREATE TABLE dbo.ti_tipo_equipo (
                id         SERIAL PRIMARY KEY,
                nombre     VARCHAR(50) NOT NULL UNIQUE,
                estado     BOOLEAN     NOT NULL DEFAULT true,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
        ");

        // Inventario de equipos (una fila por unidad física)
        DB::statement("
            CREATE TABLE dbo.ti_equipo (
                id                  SERIAL PRIMARY KEY,
                codigo_bien         VARCHAR(50)  NOT NULL UNIQUE,
                tipo_equipo_id      INT          REFERENCES dbo.ti_tipo_equipo(id),
                marca               VARCHAR(50),
                modelo              VARCHAR(50),
                descripcion         VARCHAR(300),
                serie               VARCHAR(100),
                estado              VARCHAR(20)  NOT NULL DEFAULT 'DISPONIBLE',
                condicion           VARCHAR(20),
                fecha_ingreso       DATE,
                vida_util_anios     SMALLINT,
                ubicacion           VARCHAR(100),
                ultimo_mantenimiento DATE,
                observaciones       TEXT,
                created_at          TIMESTAMP,
                created_by          VARCHAR(20),
                updated_at          TIMESTAMP,
                updated_by          VARCHAR(20)
            )
        ");

        // Historial de custodia (asignación de equipo a empleado)
        DB::statement("
            CREATE TABLE dbo.ti_asignacion (
                id                  SERIAL PRIMARY KEY,
                equipo_id           INT         NOT NULL REFERENCES dbo.ti_equipo(id),
                id_emp              VARCHAR(20) NOT NULL REFERENCES dbo.ad_empleado(id_emp),
                fecha_asignacion    DATE        NOT NULL,
                fecha_devolucion    DATE,
                motivo_devolucion   VARCHAR(30),
                observacion         TEXT,
                usuario_asigna      VARCHAR(20),
                usuario_devolucion  VARCHAR(20),
                created_at          TIMESTAMP,
                updated_at          TIMESTAMP
            )
        ");

        // Catálogo maestro del checklist de mantenimiento
        DB::statement("
            CREATE TABLE dbo.ti_actividad_mantenimiento (
                id         SERIAL PRIMARY KEY,
                nombre     VARCHAR(150) NOT NULL,
                orden      SMALLINT     NOT NULL DEFAULT 1,
                estado     BOOLEAN      NOT NULL DEFAULT true,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
        ");

        // Cabecera de ejecución de mantenimiento
        DB::statement("
            CREATE TABLE dbo.ti_mantenimiento (
                id                   SERIAL PRIMARY KEY,
                equipo_id            INT         NOT NULL REFERENCES dbo.ti_equipo(id),
                anio                 SMALLINT    NOT NULL,
                fecha_mantenimiento  DATE        NOT NULL,
                hora_inicio          TIME        NOT NULL,
                hora_fin             TIME        NOT NULL,
                tipo                 VARCHAR(20) NOT NULL DEFAULT 'PREVENTIVO',
                id_emp_tecnico       VARCHAR(20) NOT NULL REFERENCES dbo.ad_empleado(id_emp),
                id_emp_custodio      VARCHAR(20) REFERENCES dbo.ad_empleado(id_emp),
                observaciones        TEXT,
                acta_alfresco_id     VARCHAR(100),
                acta_nombre_archivo  VARCHAR(200),
                created_by           VARCHAR(20),
                created_at           TIMESTAMP,
                updated_at           TIMESTAMP,
                UNIQUE (equipo_id, anio)
            )
        ");

        // Detalle del checklist por ejecución (snapshot SI/NO)
        DB::statement("
            CREATE TABLE dbo.ti_mantenimiento_detalle (
                id               SERIAL PRIMARY KEY,
                mantenimiento_id INT     NOT NULL REFERENCES dbo.ti_mantenimiento(id) ON DELETE CASCADE,
                actividad_id     INT     NOT NULL REFERENCES dbo.ti_actividad_mantenimiento(id),
                realizado        BOOLEAN NOT NULL DEFAULT false
            )
        ");

        // Rol nuevo para el módulo
        DB::table('dbo.admin_rol')->insert([
            'descripcion' => 'TECNOLOGIA',
            'estado'      => true,
        ]);

        // Seed: catálogo de tipos de equipo
        DB::table('dbo.ti_tipo_equipo')->insert([
            ['nombre' => 'COMPUTADOR DE ESCRITORIO', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'LAPTOP',                    'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'IMPRESORA',                 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'MONITOR',                    'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'ESCÁNER',                    'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'PROYECTOR',                  'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'OTRO',                       'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Seed: checklist real de mantenimiento (orden = como se usa en papel)
        $checklist = [
            'Ingreso al equipo',
            'Limpieza interna del equipo',
            'Limpieza externa del equipo',
            'Borrado archivos temporales',
            'Ingreso al equipo por la IP',
            'Actualización del antivirus',
            'Formateo del equipo',
            'Respaldo carpeta Escritorio',
            'Respaldo carpeta Mis documentos',
            'Respaldo correo electrónico institucional (PST)',
        ];
        foreach ($checklist as $i => $nombre) {
            DB::table('dbo.ti_actividad_mantenimiento')->insert([
                'nombre'     => $nombre,
                'orden'      => $i + 1,
                'estado'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.ti_mantenimiento_detalle');
        DB::statement('DROP TABLE IF EXISTS dbo.ti_mantenimiento');
        DB::statement('DROP TABLE IF EXISTS dbo.ti_actividad_mantenimiento');
        DB::statement('DROP TABLE IF EXISTS dbo.ti_asignacion');
        DB::statement('DROP TABLE IF EXISTS dbo.ti_equipo');
        DB::statement('DROP TABLE IF EXISTS dbo.ti_tipo_equipo');

        DB::table('dbo.admin_rol')->where('descripcion', 'TECNOLOGIA')->delete();
    }
};
