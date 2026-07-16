<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE dbo.nom_he_planificacion_cab SET estado = 'PROCESADO' WHERE estado = 'AUTORIZADO'");
    }

    public function down(): void
    {
        DB::statement("UPDATE dbo.nom_he_planificacion_cab SET estado = 'AUTORIZADO' WHERE estado = 'PROCESADO'");
    }
};
