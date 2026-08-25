<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('dbo.ad_enfermedad_catastrofica')
            ->whereRaw("UPPER(nombre) = 'OTROS'")
            ->exists();

        if (!$existe) {
            DB::table('dbo.ad_enfermedad_catastrofica')->insert(['nombre' => 'Otros']);
        }
    }

    public function down(): void
    {
        DB::table('dbo.ad_enfermedad_catastrofica')
            ->whereRaw("UPPER(nombre) = 'OTROS'")
            ->delete();
    }
};
