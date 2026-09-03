<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Agrega estado de anulación a certificados laborales — antes no había forma de
    // invalidar un certificado emitido por error (ver CLAUDE.md, spec 10 hallazgo 5).
    // El número anulado NO se libera/reutiliza — queda como constancia de que existió
    // y se invalidó (mismo criterio que numero_accion en acc_accion_personal).
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.d2_certificado_laboral ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'EMITIDO'");
        DB::statement("ALTER TABLE dbo.d2_certificado_laboral ADD COLUMN observacion_anulacion VARCHAR(300) NULL");
        DB::statement("ALTER TABLE dbo.d2_certificado_laboral ADD COLUMN anulado_en TIMESTAMP NULL");
        DB::statement("ALTER TABLE dbo.d2_certificado_laboral ADD COLUMN anulado_por VARCHAR(20) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.d2_certificado_laboral DROP COLUMN IF EXISTS estado");
        DB::statement("ALTER TABLE dbo.d2_certificado_laboral DROP COLUMN IF EXISTS observacion_anulacion");
        DB::statement("ALTER TABLE dbo.d2_certificado_laboral DROP COLUMN IF EXISTS anulado_en");
        DB::statement("ALTER TABLE dbo.d2_certificado_laboral DROP COLUMN IF EXISTS anulado_por");
    }
};
