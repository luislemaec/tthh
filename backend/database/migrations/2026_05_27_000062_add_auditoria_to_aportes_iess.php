<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.d2_aportes_iess ADD COLUMN IF NOT EXISTS created_by VARCHAR(20) NULL');
        DB::statement('ALTER TABLE dbo.d2_aportes_iess ADD COLUMN IF NOT EXISTS updated_at  TIMESTAMP  NULL');
        DB::statement('ALTER TABLE dbo.d2_aportes_iess ADD COLUMN IF NOT EXISTS updated_by  VARCHAR(20) NULL');
    }

    public function down(): void
    {
        foreach (['created_by', 'updated_at', 'updated_by'] as $col) {
            DB::statement("ALTER TABLE dbo.d2_aportes_iess DROP COLUMN IF EXISTS {$col}");
        }
    }
};
