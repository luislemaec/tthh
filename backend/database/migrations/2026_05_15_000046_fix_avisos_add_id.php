<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Agrega id SERIAL como primary key si no existe
        DB::statement("ALTER TABLE dbo.d2_aviso ADD COLUMN IF NOT EXISTS id SERIAL");
        DB::statement("DO $$ BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM pg_constraint
                WHERE conrelid = 'dbo.d2_aviso'::regclass AND contype = 'p'
            ) THEN
                ALTER TABLE dbo.d2_aviso ADD PRIMARY KEY (id);
            END IF;
        END $$");
    }

    public function down(): void {}
};
