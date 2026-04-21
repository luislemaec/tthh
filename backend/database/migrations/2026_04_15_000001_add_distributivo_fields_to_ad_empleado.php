<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar campos del distributivo a ad_empleado
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS partida_individual integer');
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS partida_presupuestaria varchar(60)');
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS estado_puesto varchar(20) DEFAULT \'OCUPADO\'');
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS grupo_ocupacional varchar(100)');
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS proceso_institucional varchar(30)');
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS acumula_fondos_reserva smallint DEFAULT 0');
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS acumula_decimo_tercero boolean DEFAULT false');
        DB::statement('ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS acumula_decimo_cuarto boolean DEFAULT false');

        // Tabla histórica de aportes IESS
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.d2_aportes_iess (
                id_aporte          SERIAL PRIMARY KEY,
                modalidad          VARCHAR(30) NOT NULL,
                aporte_individual  DECIMAL(5,2) NOT NULL,
                aporte_patronal    DECIMAL(5,2) NOT NULL,
                fecha_desde        DATE NOT NULL,
                fecha_hasta        DATE NULL,
                created_at         TIMESTAMP DEFAULT NOW()
            )
        ");

        // Valores iniciales
        DB::statement("
            INSERT INTO dbo.d2_aportes_iess (modalidad, aporte_individual, aporte_patronal, fecha_desde)
            SELECT 'LOSEP', 11.45, 9.15, '2024-01-01'
            WHERE NOT EXISTS (SELECT 1 FROM dbo.d2_aportes_iess WHERE modalidad = 'LOSEP')
        ");
        DB::statement("
            INSERT INTO dbo.d2_aportes_iess (modalidad, aporte_individual, aporte_patronal, fecha_desde)
            SELECT 'CODIGO DEL TRABAJO', 9.45, 11.15, '2024-01-01'
            WHERE NOT EXISTS (SELECT 1 FROM dbo.d2_aportes_iess WHERE modalidad = 'CODIGO DEL TRABAJO')
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS partida_individual');
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS partida_presupuestaria');
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS estado_puesto');
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS grupo_ocupacional');
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS proceso_institucional');
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS acumula_fondos_reserva');
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS acumula_decimo_tercero');
        DB::statement('ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS acumula_decimo_cuarto');
        DB::statement('DROP TABLE IF EXISTS dbo.d2_aportes_iess');
    }
};
