<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.d2_aviso_ticker (
                id         SERIAL PRIMARY KEY,
                texto      TEXT NOT NULL,
                activo     BOOLEAN NOT NULL DEFAULT true,
                orden      SMALLINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.d2_aviso_ticker');
    }
};
