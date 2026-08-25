<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dbo.ad_empleado', function (Blueprint $table) {
            $table->boolean('es_comisionado_entrante')->default(false)->nullable()->after('institucion_comision');
        });

        // Migrar empleados que tenían modalidad_laboral = 'Comisión de Servicios'
        DB::statement("
            UPDATE dbo.ad_empleado
            SET es_comisionado_entrante = true
            WHERE LOWER(TRIM(modalidad_laboral)) = 'comisión de servicios'
        ");
    }

    public function down(): void
    {
        Schema::table('dbo.ad_empleado', function (Blueprint $table) {
            $table->dropColumn('es_comisionado_entrante');
        });
    }
};
