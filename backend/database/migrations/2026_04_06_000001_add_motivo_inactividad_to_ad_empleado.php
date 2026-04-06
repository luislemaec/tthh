<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE dbo.ad_empleado
            ADD COLUMN IF NOT EXISTS motivo_inactividad VARCHAR(20) NULL
        ");

        DB::statement("
            COMMENT ON COLUMN dbo.ad_empleado.motivo_inactividad IS
            'DESVINCULACION | COMISION_SALIDA | COMISION_RETORNO | NUEVO_INGRESO'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE dbo.ad_empleado
            DROP COLUMN IF EXISTS motivo_inactividad
        ");
    }
};
