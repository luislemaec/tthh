<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('dbo.nom_fondos_reserva', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio');
            $table->smallInteger('mes');
            $table->string('id_emp', 20);
            $table->decimal('sueldo_base', 10, 2);
            $table->smallInteger('dias');
            $table->decimal('porcentaje', 5, 2)->default(8.33);
            $table->decimal('valor', 10, 2);
            $table->string('tipo', 10);
            $table->string('estado', 10)->default('BORRADOR');
            $table->string('creado_por', 20);
            $table->timestamp('fecha_calculo');
            $table->string('cerrado_por', 20)->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();

            $table->unique(['anio', 'mes', 'id_emp']);
            $table->foreign('id_emp')->references('id_emp')->on('dbo.ad_empleado');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('dbo.nom_fondos_reserva');
    }
};
