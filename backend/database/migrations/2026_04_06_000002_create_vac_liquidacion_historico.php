<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.vac_liquidacion_historico (
                id                  SERIAL PRIMARY KEY,
                id_emp              VARCHAR(10)    NOT NULL,
                motivo              VARCHAR(20)    NOT NULL,
                fecha_evento        DATE           NOT NULL,
                fecha_corte_usada   DATE           NOT NULL,
                saldo_inicial       NUMERIC(8,2)   NOT NULL DEFAULT 0,
                acumulado           NUMERIC(8,2)   NOT NULL DEFAULT 0,
                tomados             NUMERIC(8,2)   NOT NULL DEFAULT 0,
                saldo_liquidado     NUMERIC(8,2)   NOT NULL DEFAULT 0,
                observacion         VARCHAR(500)   NULL,
                usuario_proceso     VARCHAR(10)    NOT NULL,
                fecha_registro      TIMESTAMP      NOT NULL DEFAULT NOW()
            )
        ");

        DB::statement("
            COMMENT ON COLUMN dbo.vac_liquidacion_historico.motivo IS
            'DESVINCULACION | COMISION_SALIDA | COMISION_RETORNO | NUEVO_INGRESO'
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS dbo.vac_liquidacion_historico");
    }
};
