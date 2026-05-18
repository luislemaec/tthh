<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.trans_solicitud_mov ADD COLUMN IF NOT EXISTS direccion_salida  VARCHAR(200)");
        DB::statement("ALTER TABLE dbo.trans_solicitud_mov ADD COLUMN IF NOT EXISTS direccion_destino VARCHAR(200)");
        DB::statement("ALTER TABLE dbo.trans_solicitud_mov ADD COLUMN IF NOT EXISTS pasajeros         TEXT");
    }

    public function down(): void {}
};
