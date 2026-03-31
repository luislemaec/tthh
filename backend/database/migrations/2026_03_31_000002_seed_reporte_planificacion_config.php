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

        // 2. Opción de menú — buscar la categoría de Planificación (donde está OPC006)
        $opcPlanificacion = DB::table('dbo.admin_opcion')
            ->where('ruta', '/planificacion')
            ->first();

        if (!$opcPlanificacion) return;

        $idCategoria     = $opcPlanificacion->id_categoria;
        $ordenCategoria  = $opcPlanificacion->orden_categoria;

        // Calcular siguiente orden dentro de la categoría
        $maxOrden = DB::table('dbo.admin_opcion')
            ->where('id_categoria', $idCategoria)
            ->max('orden');

        // Calcular siguiente ID
        $maxId = DB::table('dbo.admin_opcion')->max('id');
        // Formato OPC + número con ceros
        preg_match('/(\d+)$/', $maxId, $m);
        $nextNum = isset($m[1]) ? (int)$m[1] + 1 : 99;
        $nextId  = 'OPC' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        DB::table('dbo.admin_opcion')->insertOrIgnore([
            'id'              => $nextId,
            'descripcion'     => 'Reporte Planificación Vacaciones',
            'ruta'            => '/planificacion/reporte',
            'icono'           => 'DocumentChartBarIcon',
            'id_categoria'    => $idCategoria,
            'orden_categoria' => $ordenCategoria,
            'orden'           => $maxOrden + 1,
            'estado'          => 'ACTIVO',
        ]);
    }

    public function down(): void
    {
        DB::table('dbo.d2_configuracion')
            ->where('concepto', 'COORDINADOR_PLANIFICACION')
            ->delete();

        DB::table('dbo.admin_opcion')
            ->where('ruta', '/planificacion/reporte')
            ->delete();
    }
};
