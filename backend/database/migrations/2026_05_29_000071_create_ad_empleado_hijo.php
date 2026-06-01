<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TABLE IF NOT EXISTS dbo.ad_empleado_hijo (
            id               SERIAL PRIMARY KEY,
            id_emp           VARCHAR(20) NOT NULL,
            nombre           VARCHAR(200) NULL,
            fecha_nacimiento DATE NOT NULL,
            created_at       TIMESTAMP DEFAULT now(),
            CONSTRAINT fk_hijo_emp FOREIGN KEY (id_emp) REFERENCES dbo.ad_empleado(id_emp) ON DELETE CASCADE
        )");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_empleado_hijo_emp ON dbo.ad_empleado_hijo(id_emp)");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS dbo.ad_empleado_hijo");
    }
};
