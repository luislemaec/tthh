<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.d2_permiso ADD COLUMN IF NOT EXISTS tipo_horario VARCHAR(20) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.d2_permiso DROP COLUMN IF EXISTS tipo_horario");
    }
};
