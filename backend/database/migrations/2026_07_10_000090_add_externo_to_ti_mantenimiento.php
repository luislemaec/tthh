<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.ti_mantenimiento
            ADD COLUMN IF NOT EXISTS origen               VARCHAR(20) NOT NULL DEFAULT 'INTERNO',
            ADD COLUMN IF NOT EXISTS proveedor             VARCHAR(150) NULL,
            ADD COLUMN IF NOT EXISTS proceso_contratacion  VARCHAR(50)  NULL,
            ADD COLUMN IF NOT EXISTS numero_orden_compra   VARCHAR(50)  NULL,
            ADD COLUMN IF NOT EXISTS lote_externo          VARCHAR(50)  NULL
        ");

        // El mantenimiento externo por lote no siempre registra hora exacta de inicio/fin
        DB::statement("ALTER TABLE dbo.ti_mantenimiento ALTER COLUMN hora_inicio DROP NOT NULL");
        DB::statement("ALTER TABLE dbo.ti_mantenimiento ALTER COLUMN hora_fin DROP NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.ti_mantenimiento
            DROP COLUMN IF EXISTS origen,
            DROP COLUMN IF EXISTS proveedor,
            DROP COLUMN IF EXISTS proceso_contratacion,
            DROP COLUMN IF EXISTS numero_orden_compra,
            DROP COLUMN IF EXISTS lote_externo
        ");
    }
};
