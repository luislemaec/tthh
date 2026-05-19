<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Auditoría de aprobación
        DB::statement("ALTER TABLE dbo.d2_vacacion ADD COLUMN IF NOT EXISTS aprobado_en  TIMESTAMP NULL");
        DB::statement("ALTER TABLE dbo.d2_vacacion ADD COLUMN IF NOT EXISTS aprobado_por VARCHAR(20) NULL");
        // Auditoría general de modificaciones
        DB::statement("ALTER TABLE dbo.d2_vacacion ADD COLUMN IF NOT EXISTS updated_at   TIMESTAMP NULL");
        DB::statement("ALTER TABLE dbo.d2_vacacion ADD COLUMN IF NOT EXISTS updated_by   VARCHAR(20) NULL");
        // Nota: created_at ya existe como fecha_hora; created_by está implícito en id_emp
    }

    public function down(): void {}
};
