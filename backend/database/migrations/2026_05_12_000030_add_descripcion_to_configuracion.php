<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.d2_configuracion ADD COLUMN IF NOT EXISTS descripcion TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.d2_configuracion DROP COLUMN IF EXISTS descripcion');
    }
};
