<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('dbo.admin_rol')
            ->where('descripcion', 'COMISIONES')
            ->exists();

        if (!$existe) {
            DB::table('dbo.admin_rol')->insert([
                'descripcion' => 'COMISIONES',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dbo.admin_rol')
            ->where('descripcion', 'COMISIONES')
            ->delete();
    }
};
