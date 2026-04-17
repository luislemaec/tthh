<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('dbo.nom_he_planificacion_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cab_id');
            $table->string('actividad', 300);
            $table->decimal('horas_extraordinarias', 8, 2)->default(0);
            $table->decimal('horas_suplementarias',  8, 2)->default(0);

            $table->foreign('cab_id')
                ->references('id')
                ->on('dbo.nom_he_planificacion_cab')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('dbo.nom_he_planificacion_det');
    }
};
