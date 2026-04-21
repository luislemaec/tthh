<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('dbo.nom_he_registro', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cab_id');
            $table->string('id_emp', 10);
            $table->date('fecha');
            $table->decimal('horas_extraordinarias', 8, 2)->default(0);
            $table->decimal('horas_suplementarias',  8, 2)->default(0);
            $table->string('descripcion', 300)->nullable();
            $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE | APROBADO | NEGADO
            $table->string('usuario_decision', 20)->nullable();
            $table->timestamp('fecha_decision')->nullable();
            $table->string('observacion', 250)->nullable();
            $table->timestamps();

            $table->foreign('cab_id')
                ->references('id')
                ->on('dbo.nom_he_planificacion_cab');
            $table->foreign('id_emp')
                ->references('id_emp')
                ->on('dbo.ad_empleado');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('dbo.nom_he_registro');
    }
};
