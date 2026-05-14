<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            ALTER TABLE dbo.d2_modalidad_laboral
                ADD COLUMN IF NOT EXISTS created_at  TIMESTAMP NULL,
                ADD COLUMN IF NOT EXISTS created_by  VARCHAR(20) NULL,
                ADD COLUMN IF NOT EXISTS updated_at  TIMESTAMP NULL,
                ADD COLUMN IF NOT EXISTS updated_by  VARCHAR(20) NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE dbo.d2_modalidad_laboral
                DROP COLUMN IF EXISTS created_at,
                DROP COLUMN IF EXISTS created_by,
                DROP COLUMN IF EXISTS updated_at,
                DROP COLUMN IF EXISTS updated_by
        ");
    }
};
