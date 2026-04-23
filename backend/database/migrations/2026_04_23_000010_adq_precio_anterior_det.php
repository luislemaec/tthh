<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE adq.orden_compra_det ADD COLUMN IF NOT EXISTS precio_anterior DECIMAL(10,4) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE adq.orden_compra_det DROP COLUMN IF EXISTS precio_anterior');
    }
};
