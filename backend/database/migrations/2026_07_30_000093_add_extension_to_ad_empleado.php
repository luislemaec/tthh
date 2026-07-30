<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS extension VARCHAR(10) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS extension');
    }
};
