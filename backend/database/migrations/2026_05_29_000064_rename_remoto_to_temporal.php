<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE dbo.ad_empleado SET modalidad_marcacion = 'TEMPORAL' WHERE modalidad_marcacion = 'REMOTO'");
    }

    public function down(): void
    {
        DB::statement("UPDATE dbo.ad_empleado SET modalidad_marcacion = 'REMOTO' WHERE modalidad_marcacion = 'TEMPORAL'");
    }
};
