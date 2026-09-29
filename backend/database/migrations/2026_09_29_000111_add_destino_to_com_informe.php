<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.com_informe ADD COLUMN IF NOT EXISTS destino VARCHAR(200) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.com_informe DROP COLUMN IF EXISTS destino');
    }
};
