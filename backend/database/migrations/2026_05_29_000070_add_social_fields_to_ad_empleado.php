<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS grupo_vulnerable_id      INT  NULL");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS grupo_prioritario_id     INT  NULL");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS tiene_discapacidad       BOOLEAN NOT NULL DEFAULT false");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS tipo_discapacidad_id     INT  NULL");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS porcentaje_discapacidad  SMALLINT NULL");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS tiene_enfermedad_catastrofica   BOOLEAN NOT NULL DEFAULT false");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS enfermedad_catastrofica_id      INT  NULL");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS tiene_persona_sustituta  BOOLEAN NOT NULL DEFAULT false");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS sustituta_alfresco_id    VARCHAR(100) NULL");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS sustituta_nombre_archivo VARCHAR(200) NULL");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS sustituta_fecha_caducidad DATE NULL");
        DB::statement("ALTER TABLE dbo.ad_empleado ADD COLUMN IF NOT EXISTS num_hijos_mayores        SMALLINT NOT NULL DEFAULT 0");
    }

    public function down(): void
    {
        foreach ([
            'grupo_vulnerable_id','grupo_prioritario_id',
            'tiene_discapacidad','tipo_discapacidad_id','porcentaje_discapacidad',
            'tiene_enfermedad_catastrofica','enfermedad_catastrofica_id',
            'tiene_persona_sustituta','sustituta_alfresco_id','sustituta_nombre_archivo','sustituta_fecha_caducidad',
            'num_hijos_mayores',
        ] as $col) {
            DB::statement("ALTER TABLE dbo.ad_empleado DROP COLUMN IF EXISTS $col");
        }
    }
};
