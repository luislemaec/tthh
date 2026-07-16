<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.d2_vacacion ADD COLUMN IF NOT EXISTS backup_id     VARCHAR(20)  NULL');
        DB::statement('ALTER TABLE dbo.d2_vacacion ADD COLUMN IF NOT EXISTS backup_nombre VARCHAR(300) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.d2_vacacion DROP COLUMN IF EXISTS backup_id');
        DB::statement('ALTER TABLE dbo.d2_vacacion DROP COLUMN IF EXISTS backup_nombre');
    }
};
