<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det ADD COLUMN IF NOT EXISTS sanciones DECIMAL(10,2) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det DROP COLUMN IF EXISTS sanciones');
    }
};
