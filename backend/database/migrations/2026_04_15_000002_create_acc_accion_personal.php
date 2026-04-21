<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.acc_accion_personal (
                id_accion              SERIAL PRIMARY KEY,
                numero_accion          VARCHAR(20) NOT NULL UNIQUE,
                tipo_accion            VARCHAR(30) NOT NULL,
                fecha_elaboracion      DATE NOT NULL,
                id_emp                 VARCHAR(20) NOT NULL,
                id_emp_titular         VARCHAR(20) NULL,
                fecha_inicio           DATE NOT NULL,
                fecha_fin              DATE NULL,
                motivacion             TEXT NULL,

                -- Situación actual (snapshot al momento de crear)
                actual_cargo           VARCHAR(200) NULL,
                actual_grupo_ocup      VARCHAR(100) NULL,
                actual_grado           INTEGER NULL,
                actual_remuneracion    DECIMAL(10,2) NULL,
                actual_partida         VARCHAR(60) NULL,
                actual_proceso_inst    VARCHAR(30) NULL,

                -- Situación propuesta (ingresada manualmente por TH)
                propuesto_cargo        VARCHAR(200) NULL,
                propuesto_grupo_ocup   VARCHAR(100) NULL,
                propuesto_grado        INTEGER NULL,
                propuesto_remuneracion DECIMAL(10,2) NULL,
                propuesto_partida      VARCHAR(60) NULL,
                propuesto_proceso_inst VARCHAR(30) NULL,

                diferencial            DECIMAL(10,2) DEFAULT 0,
                estado                 VARCHAR(20) DEFAULT 'ACTIVO',
                creado_por             VARCHAR(20) NULL,
                created_at             TIMESTAMP DEFAULT NOW(),
                updated_at             TIMESTAMP DEFAULT NOW()
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.acc_accion_personal');
    }
};
