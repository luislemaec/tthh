<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mover datos bancarios de la cabecera a cada servidor
        DB::statement("
            ALTER TABLE dbo.com_solicitud_servidor
                ADD COLUMN banco         VARCHAR(100) NULL,
                ADD COLUMN tipo_cuenta   VARCHAR(50)  NULL,
                ADD COLUMN numero_cuenta VARCHAR(50)  NULL
        ");

        // Dejar los campos en com_solicitud como nullable (ya lo son),
        // pero ya no se usarán — la fuente de verdad es com_solicitud_servidor
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE dbo.com_solicitud_servidor
                DROP COLUMN IF EXISTS banco,
                DROP COLUMN IF EXISTS tipo_cuenta,
                DROP COLUMN IF EXISTS numero_cuenta
        ");
    }
};
