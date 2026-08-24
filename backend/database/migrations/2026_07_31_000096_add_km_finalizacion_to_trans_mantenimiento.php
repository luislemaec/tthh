<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // km_actual = km reportado al CREAR el requerimiento (histórico, no se toca).
        // km_finalizacion = km real del vehículo al FINALIZAR el mantenimiento — es la
        // referencia que se usa para calcular cuándo toca el próximo mantenimiento por km_hito.
        DB::statement("ALTER TABLE dbo.trans_mantenimiento
            ADD COLUMN IF NOT EXISTS km_finalizacion INT");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.trans_mantenimiento
            DROP COLUMN IF EXISTS km_finalizacion");
    }
};
