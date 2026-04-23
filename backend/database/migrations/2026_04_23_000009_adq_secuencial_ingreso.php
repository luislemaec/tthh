<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS numero_secuencial INT NULL');
        DB::statement('ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS anio INT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS numero_secuencial');
        DB::statement('ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS anio');
    }
};
