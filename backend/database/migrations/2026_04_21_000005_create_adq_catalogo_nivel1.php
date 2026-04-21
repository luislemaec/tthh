<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE TABLE adq.catalogo_nivel1 (
                nivel1      VARCHAR(2)   NOT NULL,
                descripcion VARCHAR(200) NOT NULL,
                PRIMARY KEY (nivel1)
            )
        ');

        DB::statement('
            ALTER TABLE adq.catalogo_inventario
                ADD CONSTRAINT fk_catalogo_nivel1
                FOREIGN KEY (nivel1) REFERENCES adq.catalogo_nivel1(nivel1)
                ON UPDATE CASCADE ON DELETE RESTRICT
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE adq.catalogo_inventario DROP CONSTRAINT IF EXISTS fk_catalogo_nivel1');
        DB::statement('DROP TABLE IF EXISTS adq.catalogo_nivel1');
    }
};
