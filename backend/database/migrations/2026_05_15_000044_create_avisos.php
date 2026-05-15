<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.d2_aviso (
                id      SERIAL PRIMARY KEY,
                texto   TEXT NOT NULL,
                activo  BOOLEAN NOT NULL DEFAULT true,
                orden   SMALLINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ");

        // Config para dirección del ticker (horizontal | vertical)
        DB::table('dbo.d2_configuracion')->insertOrIgnore([
            'concepto' => 'AVISOS_DIRECCION',
            'valor'    => 'horizontal',
        ]);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.d2_aviso');
        DB::table('dbo.d2_configuracion')->where('concepto', 'AVISOS_DIRECCION')->delete();
    }
};
