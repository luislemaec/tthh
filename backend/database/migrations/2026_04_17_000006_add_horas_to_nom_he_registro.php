<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('pgsql')->table('dbo.nom_he_registro', function (Blueprint $table) {
            $table->string('hora_inicio', 5)->nullable()->after('fecha');
            $table->string('hora_fin', 5)->nullable()->after('hora_inicio');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('dbo.nom_he_registro', function (Blueprint $table) {
            $table->dropColumn(['hora_inicio', 'hora_fin']);
        });
    }
};
