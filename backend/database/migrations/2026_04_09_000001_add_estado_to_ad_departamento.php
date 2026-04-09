<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('dbo.ad_departamento', function (Blueprint $table) {
            $table->string('estado', 10)->default('ACTIVO')->after('padre_id');
        });

        // Todos los departamentos existentes quedan ACTIVO
        DB::connection('pgsql')->table('dbo.ad_departamento')->update(['estado' => 'ACTIVO']);
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('dbo.ad_departamento', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
