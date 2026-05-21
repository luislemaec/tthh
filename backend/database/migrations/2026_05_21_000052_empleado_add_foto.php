<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('pgsql')->hasColumn('dbo.ad_empleado', 'foto')) {
            Schema::connection('pgsql')->table('dbo.ad_empleado', function (Blueprint $table) {
                $table->string('foto', 500)->nullable()->after('updated_by');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('dbo.ad_empleado', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
