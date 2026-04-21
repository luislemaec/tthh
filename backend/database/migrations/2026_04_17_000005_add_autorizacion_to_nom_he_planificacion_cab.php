<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('pgsql')->table('dbo.nom_he_planificacion_cab', function (Blueprint $table) {
            $table->string('memorando', 300)->nullable()->after('pdf_aprobado');
            $table->string('usuario_autorizacion', 20)->nullable()->after('memorando');
            $table->timestamp('fecha_autorizacion')->nullable()->after('usuario_autorizacion');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('dbo.nom_he_planificacion_cab', function (Blueprint $table) {
            $table->dropColumn(['memorando', 'usuario_autorizacion', 'fecha_autorizacion']);
        });
    }
};
