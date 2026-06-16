<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tarifas de viáticos (configurable desde Admin)
        DB::statement("
            CREATE TABLE dbo.com_tarifa_viatico (
                id BIGSERIAL PRIMARY KEY,
                descripcion VARCHAR(200) NOT NULL,
                valor_dia DECIMAL(10,2) NOT NULL,
                tipo VARCHAR(10) NOT NULL DEFAULT 'AMBOS',
                activo BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
        ");

        // Cabecera de solicitud de comisión
        DB::statement("
            CREATE TABLE dbo.com_solicitud (
                id BIGSERIAL PRIMARY KEY,
                numero_solicitud VARCHAR(50) NULL,
                tipo VARCHAR(10) NOT NULL,
                id_emp VARCHAR(20) NOT NULL,
                id_depto INT NOT NULL,
                fecha_solicitud DATE NOT NULL,
                tiene_viaticos BOOLEAN NOT NULL DEFAULT true,
                tiene_movilizaciones BOOLEAN NOT NULL DEFAULT false,
                tiene_anticipo BOOLEAN NOT NULL DEFAULT false,
                destino VARCHAR(200) NOT NULL,
                unidad_nombre VARCHAR(200) NULL,
                fecha_salida DATE NOT NULL,
                hora_salida TIME NOT NULL,
                fecha_llegada DATE NOT NULL,
                hora_llegada TIME NOT NULL,
                descripcion_actividades TEXT NOT NULL,
                banco VARCHAR(100) NULL,
                tipo_cuenta VARCHAR(50) NULL,
                numero_cuenta VARCHAR(50) NULL,
                estado VARCHAR(40) NOT NULL DEFAULT 'BORRADOR',
                observacion TEXT NULL,
                num_sistema_exterior VARCHAR(100) NULL,
                resolucion_juridica VARCHAR(100) NULL,
                created_at TIMESTAMP,
                created_by VARCHAR(20) NULL,
                updated_at TIMESTAMP,
                updated_by VARCHAR(20) NULL,
                CONSTRAINT fk_com_sol_emp FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp)
            )
        ");

        // Servidores que integran la comisión (varios por solicitud)
        DB::statement("
            CREATE TABLE dbo.com_solicitud_servidor (
                id BIGSERIAL PRIMARY KEY,
                solicitud_id BIGINT NOT NULL,
                id_emp VARCHAR(20) NOT NULL,
                unidad VARCHAR(200) NULL,
                puesto VARCHAR(200) NULL,
                orden INT NOT NULL DEFAULT 1,
                CONSTRAINT fk_com_srv_sol FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE
            )
        ");

        // Tramos de transporte de la solicitud
        DB::statement("
            CREATE TABLE dbo.com_solicitud_transporte (
                id BIGSERIAL PRIMARY KEY,
                solicitud_id BIGINT NOT NULL,
                tipo VARCHAR(50) NULL,
                nombre VARCHAR(200) NULL,
                ruta VARCHAR(300) NULL,
                salida_fecha DATE NULL,
                salida_hora TIME NULL,
                llegada_fecha DATE NULL,
                llegada_hora TIME NULL,
                orden INT NOT NULL DEFAULT 1,
                CONSTRAINT fk_com_trn_sol FOREIGN KEY (solicitud_id) REFERENCES dbo.com_solicitud(id) ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.com_solicitud_transporte');
        DB::statement('DROP TABLE IF EXISTS dbo.com_solicitud_servidor');
        DB::statement('DROP TABLE IF EXISTS dbo.com_solicitud');
        DB::statement('DROP TABLE IF EXISTS dbo.com_tarifa_viatico');
    }
};
