<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cuenta bancaria del empleado (para auto-llenar en comisiones)
        DB::statement("
            ALTER TABLE dbo.ad_empleado
                ADD COLUMN IF NOT EXISTS banco         VARCHAR(100) NULL,
                ADD COLUMN IF NOT EXISTS tipo_cuenta   VARCHAR(50)  NULL,
                ADD COLUMN IF NOT EXISTS numero_cuenta VARCHAR(50)  NULL
        ");

        // Cuenta bancaria del funcionario externo
        DB::statement("
            ALTER TABLE dbo.com_funcionario_externo
                ADD COLUMN IF NOT EXISTS banco         VARCHAR(100) NULL,
                ADD COLUMN IF NOT EXISTS tipo_cuenta   VARCHAR(50)  NULL,
                ADD COLUMN IF NOT EXISTS numero_cuenta VARCHAR(50)  NULL
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS banco, DROP COLUMN IF EXISTS tipo_cuenta, DROP COLUMN IF EXISTS numero_cuenta");
        DB::statement("ALTER TABLE dbo.com_funcionario_externo DROP COLUMN IF EXISTS banco, DROP COLUMN IF EXISTS tipo_cuenta, DROP COLUMN IF EXISTS numero_cuenta");
    }
};
