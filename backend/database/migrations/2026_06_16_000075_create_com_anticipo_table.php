<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE dbo.com_anticipo (
                id BIGSERIAL PRIMARY KEY,
                solicitud_id BIGINT NOT NULL UNIQUE,
                monto_solicitado DECIMAL(10,2) NOT NULL DEFAULT 0,
                estado VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',
                cur_compromiso VARCHAR(50) NULL,
                cur_devengado VARCHAR(50) NULL,
                fecha_pago DATE NULL,
                observacion TEXT NULL,
                created_at TIMESTAMP,
                created_by VARCHAR(20) NULL,
                updated_at TIMESTAMP,
                updated_by VARCHAR(20) NULL,
                CONSTRAINT fk_com_ant_sol FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.com_anticipo');
    }
};
