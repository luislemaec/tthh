<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS programa  VARCHAR(4) NULL');
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS actividad VARCHAR(6) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS programa');
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS actividad');
    }
};
