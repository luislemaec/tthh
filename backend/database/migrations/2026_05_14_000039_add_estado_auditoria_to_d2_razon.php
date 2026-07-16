<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE dbo.d2_razon
                ADD COLUMN IF NOT EXISTS estado     VARCHAR(10)  NOT NULL DEFAULT 'ACTIVO',
                ADD COLUMN IF NOT EXISTS created_at TIMESTAMP    NULL,
                ADD COLUMN IF NOT EXISTS created_by VARCHAR(20)  NULL,
                ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP    NULL,
                ADD COLUMN IF NOT EXISTS updated_by VARCHAR(20)  NULL
        ");

        // Marcar registros existentes como ACTIVO
        DB::statement("UPDATE dbo.d2_razon SET estado = 'ACTIVO' WHERE estado IS NULL OR estado = ''");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE dbo.d2_razon
                DROP COLUMN IF EXISTS estado,
                DROP COLUMN IF EXISTS created_at,
                DROP COLUMN IF EXISTS created_by,
                DROP COLUMN IF EXISTS updated_at,
                DROP COLUMN IF EXISTS updated_by
        ");
    }
};
