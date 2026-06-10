<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dbo.ad_empleado', function (Blueprint $table) {
            $table->string('motivo_salida', 50)->nullable()->after('fecha_salida');
            $table->string('motivo_reactivacion', 50)->nullable()->after('motivo_salida');
            $table->string('institucion_comision', 200)->nullable()->after('motivo_reactivacion');
        });
    }

    public function down(): void
    {
        Schema::table('dbo.ad_empleado', function (Blueprint $table) {
            $table->dropColumn(['motivo_salida', 'motivo_reactivacion', 'institucion_comision']);
        });
    }
};
