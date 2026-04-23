<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS adq.egreso (
                id                SERIAL PRIMARY KEY,
                numero_secuencial INT NULL,
                anio              INT NULL,
                direccion         VARCHAR(200) NULL,
                empleado_id       VARCHAR(20) NULL,
                empleado_nombre   VARCHAR(200) NULL,
                estado            VARCHAR(20) NOT NULL DEFAULT 'BORRADOR',
                observacion       TEXT NULL,
                subtotal          DECIMAL(12,2) NOT NULL DEFAULT 0,
                iva_valor         DECIMAL(12,2) NOT NULL DEFAULT 0,
                total             DECIMAL(12,2) NOT NULL DEFAULT 0,
                usuario_registro  VARCHAR(20) NULL,
                usuario_despacho  VARCHAR(20) NULL,
                fecha_despacho    TIMESTAMP NULL,
                motivo_reverso    TEXT NULL,
                usuario_reverso   VARCHAR(20) NULL,
                fecha_reverso     TIMESTAMP NULL,
                created_at        TIMESTAMP,
                updated_at        TIMESTAMP
            )
        ");

        DB::statement("
            CREATE TABLE IF NOT EXISTS adq.egreso_det (
                id               SERIAL PRIMARY KEY,
                egreso_id        INT NOT NULL REFERENCES adq.egreso(id) ON DELETE CASCADE,
                articulo_id      INT NOT NULL REFERENCES adq.articulo(id),
                cantidad         DECIMAL(12,2) NOT NULL,
                precio_unitario  DECIMAL(10,4) NOT NULL DEFAULT 0,
                precio_anterior  DECIMAL(10,4) NULL,
                iva_id           INT NULL REFERENCES adq.iva(id),
                iva_porcentaje   DECIMAL(5,2) NOT NULL DEFAULT 0,
                subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0,
                iva_valor        DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_linea      DECIMAL(12,2) NOT NULL DEFAULT 0,
                created_at       TIMESTAMP,
                updated_at       TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS adq.egreso_det');
        DB::statement('DROP TABLE IF EXISTS adq.egreso');
    }
};
