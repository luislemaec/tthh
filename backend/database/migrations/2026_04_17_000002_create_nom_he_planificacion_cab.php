<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('dbo.nom_he_planificacion_cab', function (Blueprint $table) {
            $table->id();
            $table->string('id_emp', 10);
            $table->integer('anio');
            $table->integer('mes'); // 1-12
            $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE | APROBADO | NEGADO
            $table->decimal('total_extraordinarias', 8, 2)->default(0);
            $table->decimal('total_suplementarias',  8, 2)->default(0);
            $table->string('observacion', 250)->nullable();
            $table->string('usuario_registro', 20)->nullable();
            $table->timestamp('fecha_registro')->nullable();
            $table->string('usuario_decision', 20)->nullable();
            $table->timestamp('fecha_decision')->nullable();
            $table->string('pdf_aprobado', 100)->nullable(); // Alfresco node ID
            $table->unique(['id_emp', 'anio', 'mes']);

            $table->foreign('id_emp')
                ->references('id_emp')
                ->on('dbo.ad_empleado');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('dbo.nom_he_planificacion_cab');
    }
};
