<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // El "una vez al año" solo aplica al mantenimiento PREVENTIVO.
        // El CORRECTIVO (reparaciones) debe poder registrarse las veces que haga falta.
        DB::statement("ALTER TABLE dbo.ti_mantenimiento DROP CONSTRAINT IF EXISTS ti_mantenimiento_equipo_id_anio_key");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS ti_mantenimiento_preventivo_anio_uq
            ON dbo.ti_mantenimiento (equipo_id, anio) WHERE tipo = 'PREVENTIVO'");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS dbo.ti_mantenimiento_preventivo_anio_uq");
        DB::statement("ALTER TABLE dbo.ti_mantenimiento ADD CONSTRAINT ti_mantenimiento_equipo_id_anio_key UNIQUE (equipo_id, anio)");
    }
};
