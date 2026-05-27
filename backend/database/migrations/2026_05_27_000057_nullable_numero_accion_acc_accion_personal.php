<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.acc_accion_personal ALTER COLUMN numero_accion DROP NOT NULL');
    }

    public function down(): void
    {
        // Actualizar borradores sin número antes de volver a poner NOT NULL
        DB::statement("UPDATE dbo.acc_accion_personal SET numero_accion = 'BORRADOR-' || id_accion WHERE numero_accion IS NULL");
        DB::statement('ALTER TABLE dbo.acc_accion_personal ALTER COLUMN numero_accion SET NOT NULL');
    }
};
