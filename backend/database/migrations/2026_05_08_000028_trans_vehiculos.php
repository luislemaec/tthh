<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE dbo.trans_vehiculo (
                id               SERIAL PRIMARY KEY,
                placa            VARCHAR(10)  NOT NULL UNIQUE,
                marca            VARCHAR(50),
                modelo           VARCHAR(50),
                anio             SMALLINT,
                chasis           VARCHAR(50),
                color            VARCHAR(30),
                kilometraje_actual INT        NOT NULL DEFAULT 0,
                estado           VARCHAR(15)  NOT NULL DEFAULT 'ACTIVO',
                created_at       TIMESTAMP,
                updated_at       TIMESTAMP
            )
        ");

        DB::statement("
            CREATE TABLE dbo.trans_mantenimiento (
                id                     SERIAL PRIMARY KEY,
                vehiculo_id            INT         NOT NULL REFERENCES dbo.trans_vehiculo(id),
                tipo                   VARCHAR(15) NOT NULL,
                descripcion            TEXT        NOT NULL,
                id_emp_conductor       VARCHAR(20) NOT NULL REFERENCES dbo.ad_empleado(id_emp),
                estado                 VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
                taller                 VARCHAR(100),
                fecha_orden            DATE,
                numero_orden           VARCHAR(20),
                observacion_responsable TEXT,
                fecha_finalizacion     DATE,
                id_emp_responsable     VARCHAR(20) REFERENCES dbo.ad_empleado(id_emp),
                created_at             TIMESTAMP,
                updated_at             TIMESTAMP
            )
        ");

        DB::statement("
            CREATE TABLE dbo.trans_solicitud_mov (
                id                    SERIAL PRIMARY KEY,
                id_emp_solicitante    VARCHAR(20) NOT NULL REFERENCES dbo.ad_empleado(id_emp),
                motivo                TEXT        NOT NULL,
                fecha_movilizacion    DATE        NOT NULL,
                hora_salida           TIME        NOT NULL,
                hora_retorno          TIME        NOT NULL,
                lugar_salida          VARCHAR(100),
                lugar_destino         VARCHAR(200),
                num_personas          SMALLINT    NOT NULL DEFAULT 1,
                estado                VARCHAR(15) NOT NULL DEFAULT 'PENDIENTE',
                vehiculo_id           INT         REFERENCES dbo.trans_vehiculo(id),
                id_emp_conductor      VARCHAR(20) REFERENCES dbo.ad_empleado(id_emp),
                observacion           TEXT,
                km_salida             INT,
                km_retorno            INT,
                hoja_ruta_observacion TEXT,
                fecha_completado      TIMESTAMP,
                created_at            TIMESTAMP,
                updated_at            TIMESTAMP
            )
        ");

        DB::statement("
            ALTER TABLE dbo.ad_empleado
            ADD COLUMN IF NOT EXISTS puede_solicitar_vehiculo BOOLEAN NOT NULL DEFAULT false
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.trans_solicitud_mov');
        DB::statement('DROP TABLE IF EXISTS dbo.trans_mantenimiento');
        DB::statement('DROP TABLE IF EXISTS dbo.trans_vehiculo');
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS puede_solicitar_vehiculo');
    }
};
