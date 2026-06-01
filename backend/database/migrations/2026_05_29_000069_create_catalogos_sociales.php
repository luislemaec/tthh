<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Grupos Vulnerables
        DB::statement("CREATE TABLE IF NOT EXISTS dbo.ad_grupo_vulnerable (
            id     SERIAL PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            activo BOOLEAN NOT NULL DEFAULT true
        )");

        // Grupos Prioritarios
        DB::statement("CREATE TABLE IF NOT EXISTS dbo.ad_grupo_prioritario (
            id     SERIAL PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            activo BOOLEAN NOT NULL DEFAULT true
        )");

        // Tipos de Discapacidad (CONADIS)
        DB::statement("CREATE TABLE IF NOT EXISTS dbo.ad_tipo_discapacidad (
            id     SERIAL PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            activo BOOLEAN NOT NULL DEFAULT true
        )");

        // Enfermedades Catastróficas (MSP)
        DB::statement("CREATE TABLE IF NOT EXISTS dbo.ad_enfermedad_catastrofica (
            id     SERIAL PRIMARY KEY,
            nombre VARCHAR(200) NOT NULL,
            activo BOOLEAN NOT NULL DEFAULT true
        )");

        // Datos iniciales — Grupos Vulnerables
        $vulnerables = [
            'Adultos mayores',
            'Personas con discapacidad',
            'Personas en situación de movilidad humana',
            'Mujeres embarazadas',
            'Niñas, niños y adolescentes',
            'Personas privadas de libertad',
            'Personas con enfermedades catastróficas',
            'Víctimas de violencia doméstica o sexual',
        ];
        foreach ($vulnerables as $n) {
            DB::table('dbo.ad_grupo_vulnerable')->insert(['nombre' => $n]);
        }

        // Datos iniciales — Grupos Prioritarios (Constitución Art. 35)
        $prioritarios = [
            'Adultos mayores',
            'Niñas, niños y adolescentes',
            'Mujeres embarazadas',
            'Personas con discapacidad',
            'Personas privadas de libertad',
            'Personas con enfermedades catastróficas',
            'Personas en situación de riesgo',
            'Migrantes en situación de vulnerabilidad',
        ];
        foreach ($prioritarios as $n) {
            DB::table('dbo.ad_grupo_prioritario')->insert(['nombre' => $n]);
        }

        // Datos iniciales — Tipos de Discapacidad (CONADIS)
        $discapacidades = [
            'Física - Motora',
            'Visual',
            'Auditiva',
            'Intelectual',
            'Psicosocial (Mental)',
            'Lenguaje',
            'Múltiple',
        ];
        foreach ($discapacidades as $n) {
            DB::table('dbo.ad_tipo_discapacidad')->insert(['nombre' => $n]);
        }

        // Datos iniciales — Enfermedades Catastróficas (MSP)
        $enfermedades = [
            'Cáncer',
            'Insuficiencia Renal Crónica',
            'VIH/SIDA',
            'Hemofilia',
            'Trasplante de órganos',
            'Enfermedad de Alzheimer',
            'Esclerosis Múltiple',
            'Enfermedad de Parkinson',
            'Lupus Eritematoso Sistémico',
            'Artritis Reumatoide Severa',
            'Diabetes Mellitus Tipo 1',
            'Tuberculosis',
            'Distrofia Muscular',
            'Fibrosis Quística',
            'Epilepsia Refractaria',
        ];
        foreach ($enfermedades as $n) {
            DB::table('dbo.ad_enfermedad_catastrofica')->insert(['nombre' => $n]);
        }
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS dbo.ad_grupo_vulnerable");
        DB::statement("DROP TABLE IF EXISTS dbo.ad_grupo_prioritario");
        DB::statement("DROP TABLE IF EXISTS dbo.ad_tipo_discapacidad");
        DB::statement("DROP TABLE IF EXISTS dbo.ad_enfermedad_catastrofica");
    }
};
