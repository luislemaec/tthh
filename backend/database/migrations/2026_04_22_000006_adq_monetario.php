<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de tasas de IVA
        DB::statement('
            CREATE TABLE adq.iva (
                id          SERIAL       PRIMARY KEY,
                descripcion VARCHAR(50)  NOT NULL,
                porcentaje  DECIMAL(5,2) NOT NULL,
                activo      BOOLEAN      NOT NULL DEFAULT true,
                created_at  TIMESTAMP,
                updated_at  TIMESTAMP
            )
        ');

        DB::table('adq.iva')->insert([
            ['descripcion' => 'IVA 15%', 'porcentaje' => 15.00, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['descripcion' => 'IVA 0% (Exento)', 'porcentaje' => 0.00, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Nuevos campos en adq.orden_compra
        DB::statement("ALTER TABLE adq.orden_compra ALTER COLUMN proveedor_id DROP NOT NULL");
        DB::statement("ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS tipo_ingreso       VARCHAR(20)  NOT NULL DEFAULT 'COMPRA'");
        DB::statement("ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS proceso_contratacion VARCHAR(60) NULL");
        DB::statement("ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS tipo_documento     VARCHAR(30)  NULL");
        DB::statement("ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS numero_documento   VARCHAR(50)  NULL");
        DB::statement("ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS fecha_documento    DATE         NULL");
        DB::statement("ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS subtotal           DECIMAL(12,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS iva_valor          DECIMAL(12,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE adq.orden_compra ADD COLUMN IF NOT EXISTS total              DECIMAL(12,2) NOT NULL DEFAULT 0");

        // Nuevos campos en adq.orden_compra_det
        DB::statement("ALTER TABLE adq.orden_compra_det ADD COLUMN IF NOT EXISTS iva_id         INT          NULL REFERENCES adq.iva(id)");
        DB::statement("ALTER TABLE adq.orden_compra_det ADD COLUMN IF NOT EXISTS iva_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE adq.orden_compra_det ADD COLUMN IF NOT EXISTS subtotal       DECIMAL(12,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE adq.orden_compra_det ADD COLUMN IF NOT EXISTS iva_valor      DECIMAL(12,2) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE adq.orden_compra_det ADD COLUMN IF NOT EXISTS total_linea    DECIMAL(12,2) NOT NULL DEFAULT 0");

        // Nuevos campos en adq.articulo
        DB::statement("ALTER TABLE adq.articulo ADD COLUMN IF NOT EXISTS precio_unitario    DECIMAL(10,4) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE adq.articulo ADD COLUMN IF NOT EXISTS iva_id             INT           NULL REFERENCES adq.iva(id)");
        DB::statement("ALTER TABLE adq.articulo ADD COLUMN IF NOT EXISTS item_presupuestario VARCHAR(150)  NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE adq.articulo DROP COLUMN IF EXISTS precio_unitario");
        DB::statement("ALTER TABLE adq.articulo DROP COLUMN IF EXISTS iva_id");
        DB::statement("ALTER TABLE adq.articulo DROP COLUMN IF EXISTS item_presupuestario");

        DB::statement("ALTER TABLE adq.orden_compra_det DROP COLUMN IF EXISTS iva_id");
        DB::statement("ALTER TABLE adq.orden_compra_det DROP COLUMN IF EXISTS iva_porcentaje");
        DB::statement("ALTER TABLE adq.orden_compra_det DROP COLUMN IF EXISTS subtotal");
        DB::statement("ALTER TABLE adq.orden_compra_det DROP COLUMN IF EXISTS iva_valor");
        DB::statement("ALTER TABLE adq.orden_compra_det DROP COLUMN IF EXISTS total_linea");

        DB::statement("ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS tipo_ingreso");
        DB::statement("ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS proceso_contratacion");
        DB::statement("ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS tipo_documento");
        DB::statement("ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS numero_documento");
        DB::statement("ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS fecha_documento");
        DB::statement("ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS subtotal");
        DB::statement("ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS iva_valor");
        DB::statement("ALTER TABLE adq.orden_compra DROP COLUMN IF EXISTS total");

        DB::statement("DROP TABLE IF EXISTS adq.iva");
    }
};
