<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de piezas/repuestos
        DB::statement("
            CREATE TABLE dbo.ti_pieza (
                id             SERIAL PRIMARY KEY,
                codigo         VARCHAR(50),
                serie          VARCHAR(100),
                descripcion    VARCHAR(300) NOT NULL,
                fecha_entrega  DATE,
                estado         VARCHAR(20)  NOT NULL DEFAULT 'DISPONIBLE',
                equipo_id      INT REFERENCES dbo.ti_equipo(id),
                observaciones  TEXT,
                created_at     TIMESTAMP,
                created_by     VARCHAR(20),
                updated_at     TIMESTAMP,
                updated_by     VARCHAR(20)
            )
        ");

        // Historial de instalación/retiro de piezas por equipo
        DB::statement("
            CREATE TABLE dbo.ti_pieza_movimiento (
                id                 SERIAL PRIMARY KEY,
                pieza_id           INT         NOT NULL REFERENCES dbo.ti_pieza(id),
                equipo_id          INT         NOT NULL REFERENCES dbo.ti_equipo(id),
                mantenimiento_id   INT         REFERENCES dbo.ti_mantenimiento(id),
                fecha_instalacion  DATE        NOT NULL,
                fecha_retiro       DATE,
                motivo_retiro      VARCHAR(50),
                observacion        TEXT,
                usuario_instala    VARCHAR(20),
                usuario_retira     VARCHAR(20),
                created_at         TIMESTAMP,
                updated_at         TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.ti_pieza_movimiento');
        DB::statement('DROP TABLE IF EXISTS dbo.ti_pieza');
    }
};
