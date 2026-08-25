<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('dbo.admin_rol')
            ->where('descripcion', 'SUMINISTROS')
            ->exists();

        if (!$existe) {
            DB::table('dbo.admin_rol')->insert([
                'descripcion' => 'SUMINISTROS',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dbo.admin_rol')
            ->where('descripcion', 'SUMINISTROS')
            ->delete();
    }
};
