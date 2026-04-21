<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('dbo.d2_jornada', function (Blueprint $table) {
            $table->decimal('porc_extraordinaria', 5, 2)->default(0)->after('porc_25');
            $table->decimal('porc_suplementaria',  5, 2)->default(0)->after('porc_extraordinaria');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('dbo.d2_jornada', function (Blueprint $table) {
            $table->dropColumn(['porc_extraordinaria', 'porc_suplementaria']);
        });
    }
};
