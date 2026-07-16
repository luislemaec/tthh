<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.trans_tipo_mantenimiento DROP COLUMN IF EXISTS categoria");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.trans_tipo_mantenimiento ADD COLUMN IF NOT EXISTS categoria VARCHAR(25)");
    }
};
