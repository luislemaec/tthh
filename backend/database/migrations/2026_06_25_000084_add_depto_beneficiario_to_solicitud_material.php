<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adq.solicitud_material', function (Blueprint $table) {
            $table->integer('id_depto_beneficiario')->nullable()->after('id_depto');
        });
    }

    public function down(): void
    {
        Schema::table('adq.solicitud_material', function (Blueprint $table) {
            $table->dropColumn('id_depto_beneficiario');
        });
    }
};
