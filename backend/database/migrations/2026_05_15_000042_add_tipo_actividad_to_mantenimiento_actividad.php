<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE dbo.trans_mantenimiento_actividad
                ADD COLUMN IF NOT EXISTS tipo_actividad VARCHAR(2) NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE dbo.trans_mantenimiento_actividad
                DROP COLUMN IF EXISTS tipo_actividad
        ");
    }
};
