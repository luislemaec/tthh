<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.ti_equipo
            ADD COLUMN IF NOT EXISTS motivo_baja VARCHAR(50)  NULL,
            ADD COLUMN IF NOT EXISTS detalle_baja TEXT         NULL,
            ADD COLUMN IF NOT EXISTS fecha_baja    DATE         NULL
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.ti_equipo
            DROP COLUMN IF EXISTS motivo_baja,
            DROP COLUMN IF EXISTS detalle_baja,
            DROP COLUMN IF EXISTS fecha_baja
        ");
    }
};
