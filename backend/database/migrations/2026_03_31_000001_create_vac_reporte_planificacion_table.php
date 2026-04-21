<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.vac_reporte_planificacion (
                id              SERIAL PRIMARY KEY,
                anio            INTEGER NOT NULL UNIQUE,
                alfresco_node_id VARCHAR(100) NOT NULL,
                nombre_archivo  VARCHAR(255) NOT NULL,
                fecha_subida    TIMESTAMP NOT NULL DEFAULT NOW(),
                subido_por      VARCHAR(20) NOT NULL
            )
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS dbo.vac_reporte_planificacion");
    }
};
