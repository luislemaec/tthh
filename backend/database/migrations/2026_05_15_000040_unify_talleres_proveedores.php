<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1 — Agregar flags y campo orden_compra a adq.proveedor
        DB::statement("
            ALTER TABLE adq.proveedor
                ADD COLUMN IF NOT EXISTS es_proveedor_bienes BOOLEAN NOT NULL DEFAULT true,
                ADD COLUMN IF NOT EXISTS es_taller           BOOLEAN NOT NULL DEFAULT false,
                ADD COLUMN IF NOT EXISTS orden_compra        VARCHAR(50) NULL
        ");

        // 2 — Todos los proveedores existentes son de bienes
        DB::statement("UPDATE adq.proveedor SET es_proveedor_bienes = true");

        // 3 — Migrar trans_taller → adq.proveedor y construir mapa de IDs
        $talleres = DB::table('dbo.trans_taller')->get();
        $mapa     = []; // trans_taller.id => adq.proveedor.id

        foreach ($talleres as $taller) {
            // Buscar por RUC si existe
            $existente = null;
            if (!empty($taller->ruc)) {
                $existente = DB::table('adq.proveedor')->where('ruc', $taller->ruc)->first();
            }

            if ($existente) {
                DB::table('adq.proveedor')->where('id', $existente->id)->update([
                    'es_taller'    => true,
                    'orden_compra' => $taller->orden_compra ?? null,
                ]);
                $mapa[$taller->id] = $existente->id;
            } else {
                $ruc = !empty($taller->ruc) ? $taller->ruc : 'TALLER-' . $taller->id;
                $newId = DB::table('adq.proveedor')->insertGetId([
                    'ruc'                => $ruc,
                    'nombre'             => $taller->nombre,
                    'direccion'          => $taller->direccion ?? null,
                    'email'              => $taller->correo   ?? null,
                    'telefono'           => $taller->telefono ?? null,
                    'estado'             => $taller->estado   ?? 'ACTIVO',
                    'es_proveedor_bienes'=> false,
                    'es_taller'          => true,
                    'orden_compra'       => $taller->orden_compra ?? null,
                ]);
                $mapa[$taller->id] = $newId;
            }
        }

        // 4 — Remapear trans_mantenimiento.taller_id a adq.proveedor.id
        foreach ($mapa as $viejoId => $nuevoId) {
            DB::table('dbo.trans_mantenimiento')
                ->where('taller_id', $viejoId)
                ->update(['taller_id' => $nuevoId]);
        }
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE adq.proveedor
                DROP COLUMN IF EXISTS es_proveedor_bienes,
                DROP COLUMN IF EXISTS es_taller,
                DROP COLUMN IF EXISTS orden_compra
        ");
    }
};
