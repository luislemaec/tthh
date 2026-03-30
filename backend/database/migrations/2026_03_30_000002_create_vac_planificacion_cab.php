<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.vac_planificacion_cab (
                id                      SERIAL PRIMARY KEY,
                id_emp                  VARCHAR(10)  NOT NULL,
                anio                    INT          NOT NULL,
                estado                  VARCHAR(20)  NOT NULL DEFAULT 'PENDIENTE',
                total_dias_planificados NUMERIC(8,4) NOT NULL DEFAULT 0,
                fecha_registro          TIMESTAMP    NOT NULL,
                usuario_registro        VARCHAR(20)  NOT NULL,
                fecha_decision          TIMESTAMP    NULL,
                usuario_decision        VARCHAR(20)  NULL,
                observacion             VARCHAR(250) NULL,
                replanificada           VARCHAR(2)   NOT NULL DEFAULT 'NO',
                UNIQUE (id_emp, anio)
            )
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS dbo.vac_planificacion_cab");
    }
};
