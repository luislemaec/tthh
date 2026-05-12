<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.d2_configuracion ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NULL');
        DB::statement('ALTER TABLE dbo.d2_configuracion ADD COLUMN IF NOT EXISTS created_by VARCHAR(20) NULL REFERENCES dbo.ad_empleado(id_emp)');
        DB::statement('ALTER TABLE dbo.d2_configuracion ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL');
        DB::statement('ALTER TABLE dbo.d2_configuracion ADD COLUMN IF NOT EXISTS updated_by VARCHAR(20) NULL REFERENCES dbo.ad_empleado(id_emp)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.d2_configuracion DROP COLUMN IF EXISTS created_at');
        DB::statement('ALTER TABLE dbo.d2_configuracion DROP COLUMN IF EXISTS created_by');
        DB::statement('ALTER TABLE dbo.d2_configuracion DROP COLUMN IF EXISTS updated_at');
        DB::statement('ALTER TABLE dbo.d2_configuracion DROP COLUMN IF EXISTS updated_by');
    }
};
