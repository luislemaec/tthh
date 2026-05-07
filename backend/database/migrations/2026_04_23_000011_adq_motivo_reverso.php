<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS motivo_reverso TEXT NULL');
        DB::statement('ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS usuario_reverso VARCHAR(20) NULL');
        DB::statement('ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS fecha_reverso TIMESTAMP NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS motivo_reverso');
        DB::statement('ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS usuario_reverso');
        DB::statement('ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS fecha_reverso');
    }
};
