<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Tabla unidades de medida ──────────────────────────────────────
        DB::statement('
            CREATE TABLE adq.unidad_medida (
                id        SERIAL PRIMARY KEY,
                nombre    VARCHAR(60)  NOT NULL,
                abreviatura VARCHAR(15) NOT NULL,
                activo    BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMPTZ DEFAULT now(),
                updated_at TIMESTAMPTZ DEFAULT now()
            )
        ');

        DB::table('adq.unidad_medida')->insert([
            ['nombre' => 'UNIDADES',          'abreviatura' => 'u'],
            ['nombre' => 'RESMAS',             'abreviatura' => 'resma'],
            ['nombre' => 'CAJAS',              'abreviatura' => 'caja'],
            ['nombre' => 'PAQUETES',           'abreviatura' => 'paq'],
            ['nombre' => 'FUNDAS',             'abreviatura' => 'funda'],
            ['nombre' => 'SOBRES',             'abreviatura' => 'sobre'],
            ['nombre' => 'ROLLOS',             'abreviatura' => 'rollo'],
            ['nombre' => 'PARES',              'abreviatura' => 'par'],
            ['nombre' => 'DOCENAS',            'abreviatura' => 'doc'],
            ['nombre' => 'JUEGOS',             'abreviatura' => 'juego'],
            ['nombre' => 'SETS',               'abreviatura' => 'set'],
            ['nombre' => 'TALONARIOS',         'abreviatura' => 'talonario'],
            ['nombre' => 'CARTUCHOS',          'abreviatura' => 'cartucho'],
            ['nombre' => 'LITROS',             'abreviatura' => 'L'],
            ['nombre' => 'MILILITROS',         'abreviatura' => 'ml'],
            ['nombre' => 'GALONES',            'abreviatura' => 'gal'],
            ['nombre' => 'KILOGRAMOS',         'abreviatura' => 'kg'],
            ['nombre' => 'GRAMOS',             'abreviatura' => 'g'],
            ['nombre' => 'METROS',             'abreviatura' => 'm'],
            ['nombre' => 'METROS CUADRADOS',   'abreviatura' => 'm²'],
            ['nombre' => 'TONELADAS',          'abreviatura' => 'ton'],
            ['nombre' => 'HORAS',              'abreviatura' => 'hr'],
        ]);

        // ── 2. fecha_vigencia en adq.iva ─────────────────────────────────────
        DB::statement('ALTER TABLE adq.iva ADD COLUMN IF NOT EXISTS fecha_vigencia DATE NULL');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS adq.unidad_medida');
        DB::statement('ALTER TABLE adq.iva DROP COLUMN IF EXISTS fecha_vigencia');
    }
};
