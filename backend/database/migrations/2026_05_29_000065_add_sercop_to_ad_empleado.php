<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS num_sercop         VARCHAR(50) NULL");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS fecha_vence_sercop DATE        NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS num_sercop");
        DB::statement("ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS fecha_vence_sercop");
    }
};
