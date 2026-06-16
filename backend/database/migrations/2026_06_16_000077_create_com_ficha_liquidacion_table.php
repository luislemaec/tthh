<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE dbo.com_ficha_liquidacion (
                id BIGSERIAL PRIMARY KEY,
                solicitud_id BIGINT NOT NULL UNIQUE,
                -- Tarifas y cálculo base
                valor_por_dia DECIMAL(10,2) NOT NULL DEFAULT 0,
                dias_viaticos INT NOT NULL DEFAULT 0,
                total_viatico DECIMAL(10,2) NOT NULL DEFAULT 0,
                -- Anticipos pagados previamente
                anticipo_viatico DECIMAL(10,2) NOT NULL DEFAULT 0,
                anticipo_combustible DECIMAL(10,2) NULL,
                total_anticipo DECIMAL(10,2) NOT NULL DEFAULT 0,
                -- Justificativos con comprobantes
                -- Interior: código 530303 | Exterior: código 530304
                justif_alimentacion DECIMAL(10,2) NOT NULL DEFAULT 0,
                justif_alojamiento DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_justificacion DECIMAL(10,2) NOT NULL DEFAULT 0,
                -- Movilización (interior: 530301 | exterior: 530302)
                movilizacion DECIMAL(10,2) NOT NULL DEFAULT 0,
                peajes_parqueaderos DECIMAL(10,2) NOT NULL DEFAULT 0,
                -- Combustible solo interior (código 530255)
                combustibles DECIMAL(10,2) NULL,
                -- Otros
                otros_gastos DECIMAL(10,2) NOT NULL DEFAULT 0,
                -- Resultado
                viaticos_por_pagar DECIMAL(10,2) NOT NULL DEFAULT 0,
                devolucion_movilizacion DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_a_pagar DECIMAL(10,2) NOT NULL DEFAULT 0,
                tipo_resultado VARCHAR(15) NULL,
                -- Referencias CURs en eSIGEF
                cur_compromiso VARCHAR(50) NULL,
                cur_devengado VARCHAR(50) NULL,
                -- Valor por cobrar: comprobante de depósito del empleado
                comprobante_devolucion VARCHAR(100) NULL,
                fecha_devolucion DATE NULL,
                -- Estado y auditoría
                estado VARCHAR(30) NOT NULL DEFAULT 'BORRADOR',
                observacion TEXT NULL,
                created_at TIMESTAMP,
                created_by VARCHAR(20) NULL,
                updated_at TIMESTAMP,
                updated_by VARCHAR(20) NULL,
                CONSTRAINT fk_com_fic_sol FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.com_ficha_liquidacion');
    }
};
