<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('dbo.nom_rol_pago_cab', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio');
            $table->smallInteger('mes');
            $table->string('estado', 10)->default('BORRADOR');
            $table->integer('total_empleados')->default(0);
            $table->decimal('total_bruto', 10, 2)->default(0);
            $table->decimal('total_patronal', 10, 2)->default(0);
            $table->decimal('total_descuentos', 10, 2)->default(0);
            $table->decimal('total_liquido', 10, 2)->default(0);
            $table->string('creado_por', 20);
            $table->timestamp('fecha_calculo');
            $table->string('cerrado_por', 20)->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();

            $table->unique(['anio', 'mes']);
        });

        Schema::connection('pgsql')->create('dbo.nom_rol_pago_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cab_id');
            $table->string('id_emp', 20);
            $table->string('tipo_contrato', 30)->nullable();
            $table->decimal('rmu_puesto', 10, 2);
            $table->smallInteger('dias');
            $table->decimal('valor_rmu', 10, 2);
            $table->decimal('aporte_patronal_pct', 5, 2)->default(0);
            $table->decimal('aporte_patronal', 10, 2)->default(0);
            $table->decimal('aporte_personal_pct', 5, 2)->default(0);
            $table->decimal('aporte_personal', 10, 2)->default(0);
            $table->decimal('quirografario', 10, 2)->default(0);
            $table->decimal('hipotecario', 10, 2)->default(0);
            $table->decimal('impuesto_renta', 10, 2)->default(0);
            $table->decimal('supa', 10, 2)->default(0);
            $table->decimal('total_descuentos', 10, 2)->default(0);
            $table->decimal('liquido', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['cab_id', 'id_emp']);
            $table->foreign('cab_id')->references('id')->on('dbo.nom_rol_pago_cab')->onDelete('cascade');
            $table->foreign('id_emp')->references('id_emp')->on('dbo.ad_empleado');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('dbo.nom_rol_pago_det');
        Schema::connection('pgsql')->dropIfExists('dbo.nom_rol_pago_cab');
    }
};
