<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.d2_permiso ADD COLUMN IF NOT EXISTS aprobado_en TIMESTAMP NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.d2_permiso DROP COLUMN IF EXISTS aprobado_en");
    }
};
