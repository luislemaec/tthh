<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.vac_planificacion_det (
                id              SERIAL PRIMARY KEY,
                cab_id          INT          NOT NULL REFERENCES dbo.vac_planificacion_cab(id) ON DELETE CASCADE,
                numero_periodo  INT          NOT NULL,
                fecha_inicial   DATE         NULL,
                fecha_final     DATE         NULL,
                dias_calculados NUMERIC(8,4) NULL
            )
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS dbo.vac_planificacion_det");
    }
};
