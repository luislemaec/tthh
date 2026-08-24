<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.d2_vacacion
            ADD COLUMN IF NOT EXISTS requiere_informe BOOLEAN NOT NULL DEFAULT false,
            ADD COLUMN IF NOT EXISTS informe_estado   VARCHAR(20)  NULL,
            ADD COLUMN IF NOT EXISTS informe_fecha    DATE         NULL,
            ADD COLUMN IF NOT EXISTS informe_por      VARCHAR(20)  NULL
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.d2_vacacion
            DROP COLUMN IF EXISTS requiere_informe,
            DROP COLUMN IF EXISTS informe_estado,
            DROP COLUMN IF EXISTS informe_fecha,
            DROP COLUMN IF EXISTS informe_por
        ");
    }
};