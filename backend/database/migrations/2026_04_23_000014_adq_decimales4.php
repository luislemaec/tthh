<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // orden_compra cabecera
        DB::statement("ALTER TABLE adq.orden_compra ALTER COLUMN subtotal   TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.orden_compra ALTER COLUMN iva_valor  TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.orden_compra ALTER COLUMN total      TYPE DECIMAL(12,4)");

        // orden_compra_det
        DB::statement("ALTER TABLE adq.orden_compra_det ALTER COLUMN subtotal    TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.orden_compra_det ALTER COLUMN iva_valor   TYPE DECIMAL(12,4)");
        DB::statement("ALTER TABLE adq.orden_compra_det ALTER COLUMN total_linea TYPE DECIMAL(12,4)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE adq.orden_compra ALTER COLUMN subtotal   TYPE DECIMAL(12,2)");
        DB::statement("ALTER TABLE adq.orden_compra ALTER COLUMN iva_valor  TYPE DECIMAL(12,2)");
        DB::statement("ALTER TABLE adq.orden_compra ALTER COLUMN total      TYPE DECIMAL(12,2)");

        DB::statement("ALTER TABLE adq.orden_compra_det ALTER COLUMN subtotal    TYPE DECIMAL(12,2)");
        DB::statement("ALTER TABLE adq.orden_compra_det ALTER COLUMN iva_valor   TYPE DECIMAL(12,2)");
        DB::statement("ALTER TABLE adq.orden_compra_det ALTER COLUMN total_linea TYPE DECIMAL(12,2)");
    }
};
