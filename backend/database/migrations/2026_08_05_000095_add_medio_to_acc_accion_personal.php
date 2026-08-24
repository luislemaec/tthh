<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.acc_accion_personal ADD COLUMN IF NOT EXISTS medio VARCHAR(10) NULL DEFAULT 'DIGITAL'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.acc_accion_personal DROP COLUMN IF EXISTS medio");
    }
};
