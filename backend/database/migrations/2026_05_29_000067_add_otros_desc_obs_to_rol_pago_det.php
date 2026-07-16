<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det ADD COLUMN IF NOT EXISTS otros_descuentos DECIMAL(10,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det ADD COLUMN IF NOT EXISTS observaciones    VARCHAR(300) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det DROP COLUMN IF EXISTS otros_descuentos');
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det DROP COLUMN IF EXISTS observaciones');
    }
};
