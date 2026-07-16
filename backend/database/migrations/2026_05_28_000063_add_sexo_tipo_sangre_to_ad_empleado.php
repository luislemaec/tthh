<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS sexo        VARCHAR(10) NULL");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS tipo_sangre VARCHAR(5)  NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS sexo");
        DB::statement("ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS tipo_sangre");
    }
};
