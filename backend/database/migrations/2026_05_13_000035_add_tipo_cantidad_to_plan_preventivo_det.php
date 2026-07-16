<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE dbo.trans_plan_preventivo_det
                ADD COLUMN IF NOT EXISTS tipo_actividad VARCHAR(2) NOT NULL DEFAULT 'MO',
                ADD COLUMN IF NOT EXISTS cantidad SMALLINT NOT NULL DEFAULT 1
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE dbo.trans_plan_preventivo_det
                DROP COLUMN IF EXISTS tipo_actividad,
                DROP COLUMN IF EXISTS cantidad
        ");
    }
};
