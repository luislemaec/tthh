<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('adq.kardex', function (Blueprint $table) {
            $table->decimal('valor_saldo', 14, 2)->default(0)->after('total_linea');
        });

        // Recalcular valor_saldo para filas existentes
        DB::statement('UPDATE adq.kardex SET valor_saldo = ROUND(stock_despues * precio_despues, 2)');
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('adq.kardex', function (Blueprint $table) {
            $table->dropColumn('valor_saldo');
        });
    }
};
