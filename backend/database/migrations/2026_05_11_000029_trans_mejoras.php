<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Vehículos: número de motor
        DB::statement("
            ALTER TABLE dbo.trans_vehiculo
            ADD COLUMN IF NOT EXISTS numero_motor VARCHAR(50)
        ");

        // Mantenimiento: auditoría de negación
        DB::statement("
            ALTER TABLE dbo.trans_mantenimiento
            ADD COLUMN IF NOT EXISTS motivo_negacion    TEXT,
            ADD COLUMN IF NOT EXISTS fecha_negacion     TIMESTAMP,
            ADD COLUMN IF NOT EXISTS usuario_negacion   VARCHAR(20) REFERENCES dbo.ad_empleado(id_emp)
        ");

        // Solicitud movilización: auditoría de aprobación/negación
        DB::statement("
            ALTER TABLE dbo.trans_solicitud_mov
            ADD COLUMN IF NOT EXISTS id_emp_responsable VARCHAR(20) REFERENCES dbo.ad_empleado(id_emp),
            ADD COLUMN IF NOT EXISTS fecha_aprobacion   TIMESTAMP,
            ADD COLUMN IF NOT EXISTS fecha_negacion     TIMESTAMP
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.trans_vehiculo DROP COLUMN IF EXISTS numero_motor");

        DB::statement("
            ALTER TABLE dbo.trans_mantenimiento
            DROP COLUMN IF EXISTS motivo_negacion,
            DROP COLUMN IF EXISTS fecha_negacion,
            DROP COLUMN IF EXISTS usuario_negacion
        ");

        DB::statement("
            ALTER TABLE dbo.trans_solicitud_mov
            DROP COLUMN IF EXISTS id_emp_responsable,
            DROP COLUMN IF EXISTS fecha_aprobacion,
            DROP COLUMN IF EXISTS fecha_negacion
        ");
    }
};
