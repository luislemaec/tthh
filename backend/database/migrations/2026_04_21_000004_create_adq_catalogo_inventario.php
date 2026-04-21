<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE TABLE adq.catalogo_inventario (
                nivel1                    VARCHAR(2)   NOT NULL,
                nivel2                    VARCHAR(6)   NOT NULL,
                descripcion               VARCHAR(300) NOT NULL,
                asociacion_presupuestaria VARCHAR(150) NULL,
                PRIMARY KEY (nivel2)
            )
        ');

        DB::statement('CREATE INDEX idx_catalogo_nivel1 ON adq.catalogo_inventario (nivel1)');

        DB::statement('ALTER TABLE adq.articulo ADD COLUMN IF NOT EXISTS nivel1 VARCHAR(2) NULL');
        DB::statement('ALTER TABLE adq.articulo ADD COLUMN IF NOT EXISTS nivel2 VARCHAR(6) NULL');
        DB::statement('
            ALTER TABLE adq.articulo
                ADD CONSTRAINT fk_articulo_nivel2
                FOREIGN KEY (nivel2) REFERENCES adq.catalogo_inventario(nivel2)
                ON UPDATE CASCADE ON DELETE SET NULL
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE adq.articulo DROP CONSTRAINT IF EXISTS fk_articulo_nivel2');
        DB::statement('ALTER TABLE adq.articulo DROP COLUMN IF EXISTS nivel2');
        DB::statement('ALTER TABLE adq.articulo DROP COLUMN IF EXISTS nivel1');
        DB::statement('DROP TABLE IF EXISTS adq.catalogo_inventario');
    }
};
