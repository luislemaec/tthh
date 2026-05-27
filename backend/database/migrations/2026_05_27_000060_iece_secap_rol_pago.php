<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Renombrar modalidad para que el código la encuentre como 'SECAP'
        DB::table('dbo.d2_aportes_iess')
            ->where('modalidad', 'CODIGO DEL TRABAJO SECAP')
            ->update(['modalidad' => 'SECAP']);

        // Nuevas columnas en nom_rol_pago_det
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det ADD COLUMN IF NOT EXISTS iece_pct       DECIMAL(5,2)  NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det ADD COLUMN IF NOT EXISTS iece            DECIMAL(10,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det ADD COLUMN IF NOT EXISTS secap_pct      DECIMAL(5,2)  NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det ADD COLUMN IF NOT EXISTS secap           DECIMAL(10,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det ADD COLUMN IF NOT EXISTS programa        VARCHAR(4)    NULL');
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det ADD COLUMN IF NOT EXISTS actividad       VARCHAR(6)    NULL');
        DB::statement('ALTER TABLE dbo.nom_rol_pago_det ADD COLUMN IF NOT EXISTS poliza_blanket  DECIMAL(10,2) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::table('dbo.d2_aportes_iess')
            ->where('modalidad', 'SECAP')
            ->update(['modalidad' => 'CODIGO DEL TRABAJO SECAP']);

        foreach (['iece_pct', 'iece', 'secap_pct', 'secap', 'programa', 'actividad', 'poliza_blanket'] as $col) {
            DB::statement("ALTER TABLE dbo.nom_rol_pago_det DROP COLUMN IF EXISTS {$col}");
        }
    }
};
