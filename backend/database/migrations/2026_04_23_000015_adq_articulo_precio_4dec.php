<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE adq.articulo ALTER COLUMN precio_unitario TYPE DECIMAL(10,4)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE adq.articulo ALTER COLUMN precio_unitario TYPE DECIMAL(10,2)");
    }
};
