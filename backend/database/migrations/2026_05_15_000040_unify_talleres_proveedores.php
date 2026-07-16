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

        // 2 — Todos los proveedores existentes son de bienes (solo los que no sean talleres)
        DB::statement("UPDATE adq.proveedor SET es_proveedor_bienes = true WHERE es_taller = false");

        // 3 — Migrar trans_taller → adq.proveedor y construir mapa de IDs
        //     Idempotente: busca por RUC o por ruc='TALLER-{id}' para no duplicar en re-runs
        $talleres = DB::table('dbo.trans_taller')->get();
        $mapa     = [];

        foreach ($talleres as $taller) {
            $existente = null;

            if (!empty($taller->ruc)) {
                $existente = DB::table('adq.proveedor')->where('ruc', $taller->ruc)->first();
            }

            // Buscar por ruc sintético si ya fue insertado en un run anterior
            if (!$existente) {
                $existente = DB::table('adq.proveedor')->where('ruc', 'TALLER-' . $taller->id)->first();
            }

            if ($existente) {
                DB::table('adq.proveedor')->where('id', $existente->id)->update([
                    'es_taller'    => true,
                    'orden_compra' => $taller->orden_compra ?? null,
                ]);
                $mapa[$taller->id] = $existente->id;
            } else {
                $ruc   = !empty($taller->ruc) ? $taller->ruc : 'TALLER-' . $taller->id;
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

        // 4 — Soltar FK (referenciaba trans_taller), remapear IDs, re-crear FK → adq.proveedor
        DB::statement('ALTER TABLE dbo.trans_mantenimiento DROP CONSTRAINT IF EXISTS trans_mantenimiento_taller_id_fkey');

        foreach ($mapa as $viejoId => $nuevoId) {
            DB::table('dbo.trans_mantenimiento')
                ->where('taller_id', $viejoId)
                ->update(['taller_id' => $nuevoId]);
        }

        DB::statement('
            ALTER TABLE dbo.trans_mantenimiento
                ADD CONSTRAINT trans_mantenimiento_taller_id_fkey
                FOREIGN KEY (taller_id) REFERENCES adq.proveedor(id)
        ');
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
