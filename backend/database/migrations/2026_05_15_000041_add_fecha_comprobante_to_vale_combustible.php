<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE dbo.trans_vale_combustible
                ADD COLUMN IF NOT EXISTS fecha_comprobante DATE NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE dbo.trans_vale_combustible
                DROP COLUMN IF EXISTS fecha_comprobante
        ");
    }
};
