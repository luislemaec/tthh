<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE dbo.trans_taller (
                id           SERIAL PRIMARY KEY,
                nombre       VARCHAR(100) NOT NULL,
                ruc          VARCHAR(13),
                direccion    VARCHAR(200),
                correo       VARCHAR(100),
                telefono     VARCHAR(20),
                orden_compra VARCHAR(50),
                estado       VARCHAR(10) NOT NULL DEFAULT 'ACTIVO',
                created_at   TIMESTAMP,
                updated_at   TIMESTAMP
            )
        ");

        DB::statement("
            CREATE TABLE dbo.trans_tipo_mantenimiento (
                id        SERIAL PRIMARY KEY,
                nombre    VARCHAR(30)  NOT NULL,
                estado    VARCHAR(10)  NOT NULL DEFAULT 'ACTIVO',
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
        ");

        DB::statement("
            CREATE TABLE dbo.trans_plan_preventivo_cab (
                id          SERIAL PRIMARY KEY,
                vehiculo_id INT         NOT NULL REFERENCES dbo.trans_vehiculo(id),
                km_hito     INT         NOT NULL,
                nombre      VARCHAR(100),
                estado      VARCHAR(10) NOT NULL DEFAULT 'ACTIVO',
                created_at  TIMESTAMP,
                updated_at  TIMESTAMP
            )
        ");

        DB::statement("
            CREATE TABLE dbo.trans_plan_preventivo_det (
                id       SERIAL PRIMARY KEY,
                cab_id   INT          NOT NULL REFERENCES dbo.trans_plan_preventivo_cab(id) ON DELETE CASCADE,
                orden    SMALLINT     NOT NULL DEFAULT 1,
                actividad TEXT        NOT NULL
            )
        ");

        DB::statement("
            CREATE TABLE dbo.trans_mantenimiento_actividad (
                id               SERIAL PRIMARY KEY,
                mantenimiento_id INT         NOT NULL REFERENCES dbo.trans_mantenimiento(id) ON DELETE CASCADE,
                tipo             VARCHAR(10) NOT NULL,
                actividad        TEXT        NOT NULL,
                orden            SMALLINT    NOT NULL DEFAULT 1
            )
        ");

        DB::statement("
            ALTER TABLE dbo.trans_mantenimiento
                ADD COLUMN IF NOT EXISTS km_actual              INT,
                ADD COLUMN IF NOT EXISTS taller_id              INT REFERENCES dbo.trans_taller(id),
                ADD COLUMN IF NOT EXISTS tipo_mantenimiento_id  INT REFERENCES dbo.trans_tipo_mantenimiento(id),
                ADD COLUMN IF NOT EXISTS plan_preventivo_id     INT REFERENCES dbo.trans_plan_preventivo_cab(id)
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.trans_mantenimiento
            DROP COLUMN IF EXISTS km_actual,
            DROP COLUMN IF EXISTS taller_id,
            DROP COLUMN IF EXISTS tipo_mantenimiento_id,
            DROP COLUMN IF EXISTS plan_preventivo_id
        ");
        DB::statement('DROP TABLE IF EXISTS dbo.trans_mantenimiento_actividad');
        DB::statement('DROP TABLE IF EXISTS dbo.trans_plan_preventivo_det');
        DB::statement('DROP TABLE IF EXISTS dbo.trans_plan_preventivo_cab');
        DB::statement('DROP TABLE IF EXISTS dbo.trans_tipo_mantenimiento');
        DB::statement('DROP TABLE IF EXISTS dbo.trans_taller');
    }
};
