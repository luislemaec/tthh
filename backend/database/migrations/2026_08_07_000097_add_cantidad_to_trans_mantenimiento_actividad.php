<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // La cantidad ya se capturaba al crear el Plan Preventivo (trans_plan_preventivo_det.cantidad)
        // pero se perdía al copiar las actividades hacia el requerimiento de mantenimiento — la columna
        // ni siquiera existía aquí. Solo aplica a actividades PREVENTIVO (copiadas del plan); las
        // CORRECTIVO se siguen registrando como texto libre sin cantidad.
        DB::statement("ALTER TABLE dbo.trans_mantenimiento_actividad
            ADD COLUMN IF NOT EXISTS cantidad SMALLINT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.trans_mantenimiento_actividad
            DROP COLUMN IF EXISTS cantidad");
    }
};
