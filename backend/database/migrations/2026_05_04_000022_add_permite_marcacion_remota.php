<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->table('dbo.ad_empleado', function (Blueprint $table) {
            $table->boolean('permite_marcacion_remota')->default(true)->after('ubicacion');
        });

        // Insertar parámetro de VLANs si no existe
        $existe = DB::table('dbo.d2_configuracion')
            ->where('concepto', 'vlans_permitidas')
            ->exists();

        if (!$existe) {
            DB::table('dbo.d2_configuracion')->insert([
                'concepto' => 'vlans_permitidas',
                'valor'    => '192.168.1.,192.168.0.',
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('dbo.ad_empleado', function (Blueprint $table) {
            $table->dropColumn('permite_marcacion_remota');
        });

        DB::table('dbo.d2_configuracion')
            ->where('concepto', 'vlans_permitidas')
            ->delete();
    }
};
