<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Configuración del coordinador
        DB::table('dbo.d2_configuracion')->insertOrIgnore([
            'concepto' => 'COORDINADOR_PLANIFICACION',
            'valor'    => 'COORDINADOR GENERAL ADMINISTRATIVO FINANCIERO',
        ]);

        // 2. Opción de menú — buscar la categoría de Planificación (donde está la URL /planificacion)
        $opcPlanificacion = DB::table('dbo.admin_opcion')
            ->where('url', '/planificacion')
            ->first();

        if (!$opcPlanificacion) return;

        $categoria      = $opcPlanificacion->categoria;
        $ordenCategoria = $opcPlanificacion->orden_categoria;

        // Calcular siguiente secuencia
        $maxSecuencia = DB::table('dbo.admin_opcion')
            ->where('categoria', $categoria)
            ->max('secuencia');

        // Calcular siguiente ID
        $maxId = DB::table('dbo.admin_opcion')->max('id');
        preg_match('/(\d+)$/', $maxId, $m);
        $nextNum = isset($m[1]) ? (int)$m[1] + 1 : 99;
        $nextId  = 'OPC' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        DB::table('dbo.admin_opcion')->insertOrIgnore([
            'id'              => $nextId,
            'descripcion'     => 'Reporte Planificación Vacaciones',
            'url'             => '/planificacion/reporte',
            'categoria'       => $categoria,
            'orden_categoria' => $ordenCategoria,
            'secuencia'       => $maxSecuencia + 1,
            'estado'          => 'ACTIVO',
        ]);
    }

    public function down(): void
    {
        DB::table('dbo.d2_configuracion')
            ->where('concepto', 'COORDINADOR_PLANIFICACION')
            ->delete();

        DB::table('dbo.admin_opcion')
            ->where('url', '/planificacion/reporte')
            ->delete();
    }
};
