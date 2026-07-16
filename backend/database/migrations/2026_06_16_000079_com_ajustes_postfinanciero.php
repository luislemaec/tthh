<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar aplica_jerarquico a com_tarifa_viatico
        DB::statement("ALTER TABLE dbo.com_tarifa_viatico ADD COLUMN IF NOT EXISTS aplica_jerarquico BOOLEAN NOT NULL DEFAULT false");

        // Seed 2 tarifas INTERIOR
        DB::statement("
            INSERT INTO dbo.com_tarifa_viatico (descripcion, valor_dia, tipo, aplica_jerarquico, activo)
            VALUES
              ('Nivel Jerárquico Superior', 130.00, 'INTERIOR', true, true),
              ('Otros Funcionarios',         80.00, 'INTERIOR', false, true)
            ON CONFLICT DO NOTHING
        ");

        // 2. Crear tabla de coeficientes por país (viáticos al exterior)
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.com_coeficiente_pais (
                id          BIGSERIAL PRIMARY KEY,
                pais        VARCHAR(100) NOT NULL,
                region      VARCHAR(50)  NOT NULL,
                coeficiente DECIMAL(6,4) NOT NULL,
                activo      BOOLEAN      NOT NULL DEFAULT true,
                created_at  TIMESTAMP,
                updated_at  TIMESTAMP
            )
        ");

        // Seed 40 países (MEF 21/01/2020)
        $paises = [
            ['SUDÁFRICA',             'ÁFRICA',             1.25],
            ['NICARAGUA',             'AMÉRICA CENTRAL',    1.02],
            ['REPÚBLICA DOMINICANA',  'AMÉRICA CENTRAL',    1.25],
            ['HONDURAS',              'AMÉRICA CENTRAL',    1.33],
            ['EL SALVADOR',           'AMÉRICA CENTRAL',    1.36],
            ['GUATEMALA',             'AMÉRICA CENTRAL',    1.49],
            ['COSTA RICA',            'AMÉRICA CENTRAL',    1.69],
            ['PANAMÁ',                'AMÉRICA CENTRAL',    1.71],
            ['MEXICO',                'AMÉRICA DEL NORTE',  1.37],
            ['ESTADOS UNIDOS',        'AMÉRICA DEL NORTE',  2.22],
            ['CANADÁ',                'AMÉRICA DEL NORTE',  2.33],
            ['COLOMBIA',              'AMÉRICA DEL SUR',    1.14],
            ['BRASIL',                'AMÉRICA DEL SUR',    1.15],
            ['PARAGUAY',              'AMÉRICA DEL SUR',    1.18],
            ['BOLIVIA',               'AMÉRICA DEL SUR',    1.27],
            ['PERÚ',                  'AMÉRICA DEL SUR',    1.35],
            ['ARGENTINA',             'AMÉRICA DEL SUR',    1.39],
            ['CHILE',                 'AMÉRICA DEL SUR',    1.47],
            ['URUGUAY',               'AMÉRICA DEL SUR',    1.70],
            ['CHINA',                 'ASIA',               1.46],
            ['KATAR',                 'ASIA',               1.48],
            ['SINGAPUR',              'ASIA',               1.62],
            ['COREA',                 'ASIA',               1.98],
            ['ISRAEL',                'ASIA',               2.49],
            ['JAPÓN',                 'ASIA',               2.53],
            ['RUSIA',                 'EUROPA',             1.03],
            ['POLONIA',               'EUROPA',             1.24],
            ['HUNGRÍA',               'EUROPA',             1.45],
            ['ESPAÑA',                'EUROPA',             1.66],
            ['ITALIA',                'EUROPA',             1.64],
            ['FRANCIA',               'EUROPA',             1.68],
            ['PORTUGAL',              'EUROPA',             1.74],
            ['BÉLGICA',               'EUROPA',             1.75],
            ['SUECIA',                'EUROPA',             1.76],
            ['PAÍSES BAJOS',          'EUROPA',             1.80],
            ['ALEMANIA',              'EUROPA',             1.81],
            ['AUSTRIA',               'EUROPA',             1.86],
            ['REINO UNIDO',           'EUROPA',             2.19],
            ['SUIZA',                 'EUROPA',             2.38],
            ['AUSTRALIA',             'OCEANÍA',            1.89],
        ];
        $now = now();
        foreach ($paises as [$pais, $region, $coef]) {
            DB::table('dbo.com_coeficiente_pais')->insertOrIgnore([
                'pais'        => $pais,
                'region'      => $region,
                'coeficiente' => $coef,
                'activo'      => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // 3. Agregar campos de coeficiente a com_ficha_liquidacion
        DB::statement("ALTER TABLE dbo.com_ficha_liquidacion ADD COLUMN IF NOT EXISTS pais_destino    VARCHAR(100) NULL");
        DB::statement("ALTER TABLE dbo.com_ficha_liquidacion ADD COLUMN IF NOT EXISTS coeficiente_pais DECIMAL(6,4) NULL");

        // 4. Crear tabla de funcionarios externos (personal seguridad presidencial, etc.)
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.com_funcionario_externo (
                id         BIGSERIAL PRIMARY KEY,
                cedula     VARCHAR(20)  NOT NULL UNIQUE,
                nombres    VARCHAR(200) NOT NULL,
                cargo      VARCHAR(200) NOT NULL,
                activo     BOOLEAN      NOT NULL DEFAULT true,
                created_at TIMESTAMP,
                updated_at TIMESTAMP
            )
        ");

        // 5. Agregar VALOR_BASE_EXTERIOR a d2_configuracion si no existe
        $existe = DB::table('dbo.d2_configuracion')
            ->whereRaw("LOWER(concepto) = 'valor_base_exterior'")
            ->exists();
        if (!$existe) {
            DB::table('dbo.d2_configuracion')->insert([
                'concepto'    => 'VALOR_BASE_EXTERIOR',
                'valor'       => '185.00',
                'descripcion' => 'Valor base diario para viáticos al exterior ($USD)',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.com_tarifa_viatico DROP COLUMN IF EXISTS aplica_jerarquico");
        DB::statement("DROP TABLE IF EXISTS dbo.com_coeficiente_pais");
        DB::statement("ALTER TABLE dbo.com_ficha_liquidacion DROP COLUMN IF EXISTS pais_destino");
        DB::statement("ALTER TABLE dbo.com_ficha_liquidacion DROP COLUMN IF EXISTS coeficiente_pais");
        DB::statement("DROP TABLE IF EXISTS dbo.com_funcionario_externo");
        DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'valor_base_exterior'")->delete();
    }
};
