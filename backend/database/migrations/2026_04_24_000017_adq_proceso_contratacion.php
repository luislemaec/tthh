<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS adq.proceso_contratacion (
                id      SERIAL PRIMARY KEY,
                nombre  VARCHAR(100) NOT NULL,
                activo  BOOLEAN NOT NULL DEFAULT true
            )
        ");

        DB::table('adq.proceso_contratacion')->insert([
            ['nombre' => 'CATALOGO ELECTRONICO',       'activo' => true],
            ['nombre' => 'SUBASTA INVERSA ELECTRONICA', 'activo' => true],
            ['nombre' => 'CAJA CHICA',                  'activo' => true],
            ['nombre' => 'INFIMA CUANTIA',              'activo' => true],
        ]);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS adq.proceso_contratacion');
    }
};
