<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Amounts to 2 decimals — precio_unitario stays at 5 decimals
        foreach ([
            "ALTER TABLE adq.orden_compra     ALTER COLUMN subtotal    TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.orden_compra     ALTER COLUMN iva_valor   TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.orden_compra     ALTER COLUMN total       TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.orden_compra_det ALTER COLUMN subtotal    TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.orden_compra_det ALTER COLUMN iva_valor   TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.orden_compra_det ALTER COLUMN total_linea TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.egreso           ALTER COLUMN subtotal    TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.egreso           ALTER COLUMN iva_valor   TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.egreso           ALTER COLUMN total       TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.egreso_det       ALTER COLUMN subtotal    TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.egreso_det       ALTER COLUMN iva_valor   TYPE DECIMAL(12,2)",
            "ALTER TABLE adq.egreso_det       ALTER COLUMN total_linea TYPE DECIMAL(12,2)",
        ] as $sql) {
            DB::statement($sql);
        }
    }

    public function down(): void {}
};
