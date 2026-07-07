<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Historial de períodos de teletrabajo por empleado
        DB::statement("
            CREATE TABLE dbo.ad_empleado_teletrabajo (
                id          BIGSERIAL PRIMARY KEY,
                id_emp      VARCHAR(20) NOT NULL,
                fecha_desde DATE        NOT NULL,
                fecha_hasta DATE        NOT NULL,
                created_by  VARCHAR(20) NULL,
                created_at  TIMESTAMP   NULL,
                updated_at  TIMESTAMP   NULL
            )
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS dbo.ad_empleado_teletrabajo");
    }
};
