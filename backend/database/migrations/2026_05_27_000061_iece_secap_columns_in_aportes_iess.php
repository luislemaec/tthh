<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar columnas iece/secap directamente en cada fila de modalidad
        DB::statement('ALTER TABLE dbo.d2_aportes_iess ADD COLUMN IF NOT EXISTS iece_patronal  DECIMAL(5,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE dbo.d2_aportes_iess ADD COLUMN IF NOT EXISTS iece_personal  DECIMAL(5,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE dbo.d2_aportes_iess ADD COLUMN IF NOT EXISTS secap_patronal DECIMAL(5,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE dbo.d2_aportes_iess ADD COLUMN IF NOT EXISTS secap_personal DECIMAL(5,2) NOT NULL DEFAULT 0');

        // Leer valores vigentes de las filas IECE y SECAP
        $iecePct  = DB::table('dbo.d2_aportes_iess')
            ->where('modalidad', 'IECE')
            ->whereNull('fecha_hasta')
            ->value('aporte_patronal') ?? 0.5;

        $secapPct = DB::table('dbo.d2_aportes_iess')
            ->where('modalidad', 'SECAP')
            ->whereNull('fecha_hasta')
            ->value('aporte_patronal') ?? 0.5;

        // IECE aplica a LOSEP y CODIGO DEL TRABAJO (ambas filas)
        DB::table('dbo.d2_aportes_iess')
            ->whereIn('modalidad', ['LOSEP', 'CODIGO DEL TRABAJO'])
            ->whereNull('fecha_hasta')
            ->update(['iece_patronal' => $iecePct]);

        // SECAP solo aplica a CODIGO DEL TRABAJO (LOSEP queda en 0)
        DB::table('dbo.d2_aportes_iess')
            ->where('modalidad', 'CODIGO DEL TRABAJO')
            ->whereNull('fecha_hasta')
            ->update(['secap_patronal' => $secapPct]);

        // Eliminar las filas sueltas IECE y SECAP (ya no son necesarias)
        DB::table('dbo.d2_aportes_iess')
            ->whereIn('modalidad', ['IECE', 'SECAP'])
            ->delete();
    }

    public function down(): void
    {
        // Restaurar filas IECE y SECAP a partir de los datos de LOSEP/CT vigente
        $iece  = DB::table('dbo.d2_aportes_iess')->where('modalidad', 'LOSEP')->whereNull('fecha_hasta')->first();
        $secap = DB::table('dbo.d2_aportes_iess')->where('modalidad', 'CODIGO DEL TRABAJO')->whereNull('fecha_hasta')->first();

        if ($iece) {
            DB::table('dbo.d2_aportes_iess')->insert([
                'modalidad'         => 'IECE',
                'aporte_individual' => 0,
                'aporte_patronal'   => $iece->iece_patronal,
                'fecha_desde'       => $iece->fecha_desde,
                'fecha_hasta'       => null,
            ]);
        }
        if ($secap) {
            DB::table('dbo.d2_aportes_iess')->insert([
                'modalidad'         => 'SECAP',
                'aporte_individual' => 0,
                'aporte_patronal'   => $secap->secap_patronal,
                'fecha_desde'       => $secap->fecha_desde,
                'fecha_hasta'       => null,
            ]);
        }

        foreach (['iece_patronal', 'iece_personal', 'secap_patronal', 'secap_personal'] as $col) {
            DB::statement("ALTER TABLE dbo.d2_aportes_iess DROP COLUMN IF EXISTS {$col}");
        }
    }
};
