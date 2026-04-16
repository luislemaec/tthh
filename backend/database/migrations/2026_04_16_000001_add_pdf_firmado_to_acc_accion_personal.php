<?php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE dbo.acc_accion_personal ADD COLUMN IF NOT EXISTS pdf_firmado VARCHAR(255)'
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE dbo.acc_accion_personal DROP COLUMN IF EXISTS pdf_firmado'
        );
    }
};
