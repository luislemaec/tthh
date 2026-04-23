<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Precios unitarios → DECIMAL(10,5)
        DB::statement("ALTER TABLE adq.articulo          ALTER COLUMN precio_unitario TYPE DECIMAL(10,5)");
        DB::statement("ALTER TABLE adq.egreso_det        ALTER COLUMN precio_unitario TYPE DECIMAL(10,5)");
        DB::statement("ALTER TABLE adq.egreso_det        ALTER COLUMN precio_anterior TYPE DECIMAL(10,5)");
        DB::statement("ALTER TABLE adq.orden_compra_det  ALTER COLUMN precio_unitario TYPE DECIMAL(10,5)");

        // Subtotales/IVA/totales de línea → DECIMAL(12,5)
        DB::statement("ALTER TABLE adq.orden_compra      ALTER COLUMN subtotal    TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.orden_compra      ALTER COLUMN iva_valor   TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.orden_compra      ALTER COLUMN total       TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.orden_compra_det  ALTER COLUMN subtotal    TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.orden_compra_det  ALTER COLUMN iva_valor   TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.orden_compra_det  ALTER COLUMN total_linea TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.egreso            ALTER COLUMN subtotal    TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.egreso            ALTER COLUMN iva_valor   TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.egreso            ALTER COLUMN total       TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.egreso_det        ALTER COLUMN subtotal    TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.egreso_det        ALTER COLUMN iva_valor   TYPE DECIMAL(12,5)");
        DB::statement("ALTER TABLE adq.egreso_det        ALTER COLUMN total_linea TYPE DECIMAL(12,5)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE adq.articulo          ALTER COLUMN precio_unitario TYPE DECIMAL(10,4)");
        DB::statement("ALTER TABLE adq.egreso_det        ALTER COLUMN precio_unitario TYPE DECIMAL(10,4)");
        DB::statement("ALTER TABLE adq.egreso_det        ALTER COLUMN precio_anterior TYPE DECIMAL(10,4)");
        DB::statement("ALTER TABLE adq.orden_compra_det  ALTER COLUMN precio_unitario TYPE DECIMAL(10,4)");
        DB::statement("ALTER TABLE adq.orden_compra      ALTER COLUMN subtotal    TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.orden_compra      ALTER COLUMN iva_valor   TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.orden_compra      ALTER COLUMN total       TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.orden_compra_det  ALTER COLUMN subtotal    TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.orden_compra_det  ALTER COLUMN iva_valor   TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.orden_compra_det  ALTER COLUMN total_linea TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.egreso            ALTER COLUMN subtotal    TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.egreso            ALTER COLUMN iva_valor   TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.egreso            ALTER COLUMN total       TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.egreso_det        ALTER COLUMN subtotal    TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.egreso_det        ALTER COLUMN iva_valor   TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.egreso_det        ALTER COLUMN total_linea TYPE DECIMAL(12,4)");
    }
};
