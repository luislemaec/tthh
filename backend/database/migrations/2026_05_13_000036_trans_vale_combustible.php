<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE dbo.trans_vale_combustible (
                id               SERIAL PRIMARY KEY,
                numero           INT          NOT NULL,
                gasolinera       VARCHAR(200),
                id_emp_conductor VARCHAR(20)  NOT NULL REFERENCES dbo.ad_empleado(id_emp),
                vehiculo_id      INT          REFERENCES dbo.trans_vehiculo(id),
                kilometraje      INT,
                fecha            DATE         NOT NULL,
                glns_extra       DECIMAL(8,2),
                pu_extra         DECIMAL(8,4),
                valor_extra      DECIMAL(10,2),
                glns_super       DECIMAL(8,2),
                pu_super         DECIMAL(8,4),
                valor_super      DECIMAL(10,2),
                glns_diesel      DECIMAL(8,2),
                pu_diesel        DECIMAL(8,4),
                valor_diesel     DECIMAL(10,2),
                estado           VARCHAR(15)  NOT NULL DEFAULT 'EMITIDO',
                created_at       TIMESTAMP,
                updated_at       TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.trans_vale_combustible');
    }
};
