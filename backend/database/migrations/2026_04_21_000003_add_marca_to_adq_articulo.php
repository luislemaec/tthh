<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('adq.articulo', function (Blueprint $table) {
            $table->string('marca', 100)->nullable()->after('categoria');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('adq.articulo', function (Blueprint $table) {
            $table->dropColumn('marca');
        });
    }
};
