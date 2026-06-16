<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Informe de cumplimiento (F03/F04) — plazo 4 días laborales
        DB::statement("
            CREATE TABLE dbo.com_informe (
                id BIGSERIAL PRIMARY KEY,
                solicitud_id BIGINT NOT NULL UNIQUE,
                fecha_informe DATE NOT NULL,
                actividades TEXT NULL,
                productos TEXT NULL,
                fecha_salida DATE NULL,
                hora_salida TIME NULL,
                fecha_llegada DATE NULL,
                hora_llegada TIME NULL,
                estado VARCHAR(30) NOT NULL DEFAULT 'BORRADOR',
                observacion TEXT NULL,
                created_at TIMESTAMP,
                created_by VARCHAR(20) NULL,
                updated_at TIMESTAMP,
                updated_by VARCHAR(20) NULL,
                CONSTRAINT fk_com_inf_sol FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE
            )
        ");

        // Tramos de transporte del informe
        DB::statement("
            CREATE TABLE dbo.com_informe_transporte (
                id BIGSERIAL PRIMARY KEY,
                informe_id BIGINT NOT NULL,
                tipo VARCHAR(50) NULL,
                nombre VARCHAR(200) NULL,
                ruta VARCHAR(300) NULL,
                salida_fecha DATE NULL,
                salida_hora TIME NULL,
                llegada_fecha DATE NULL,
                llegada_hora TIME NULL,
                orden INT NOT NULL DEFAULT 1,
                CONSTRAINT fk_com_itrn_inf FOREIGN KEY (informe_id) REFERENCES dbo.com_informe(id) ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.com_informe_transporte');
        DB::statement('DROP TABLE IF EXISTS dbo.com_informe');
    }
};
