<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.vac_periodo_planificacion (
                id          SERIAL PRIMARY KEY,
                anio        INT          NOT NULL,
                fecha_inicio DATE         NOT NULL,
                fecha_fin    DATE         NOT NULL,
                estado       VARCHAR(10)  NOT NULL DEFAULT 'ACTIVO'
            )
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS dbo.vac_periodo_planificacion");
    }
};
