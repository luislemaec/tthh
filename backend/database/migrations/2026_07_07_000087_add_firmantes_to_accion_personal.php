<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.acc_accion_personal ADD COLUMN IF NOT EXISTS firmante_th_nombre VARCHAR(200) NULL');
        DB::statement('ALTER TABLE dbo.acc_accion_personal ADD COLUMN IF NOT EXISTS firmante_th_cargo VARCHAR(200) NULL');
        DB::statement('ALTER TABLE dbo.acc_accion_personal ADD COLUMN IF NOT EXISTS firmante_autoridad_nombre VARCHAR(200) NULL');
        DB::statement('ALTER TABLE dbo.acc_accion_personal ADD COLUMN IF NOT EXISTS firmante_autoridad_cargo VARCHAR(200) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.acc_accion_personal DROP COLUMN IF EXISTS firmante_th_nombre');
        DB::statement('ALTER TABLE dbo.acc_accion_personal DROP COLUMN IF EXISTS firmante_th_cargo');
        DB::statement('ALTER TABLE dbo.acc_accion_personal DROP COLUMN IF EXISTS firmante_autoridad_nombre');
        DB::statement('ALTER TABLE dbo.acc_accion_personal DROP COLUMN IF EXISTS firmante_autoridad_cargo');
    }
};
