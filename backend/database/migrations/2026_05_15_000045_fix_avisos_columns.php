<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.d2_aviso ADD COLUMN IF NOT EXISTS texto   TEXT NOT NULL DEFAULT ''");
        DB::statement("ALTER TABLE dbo.d2_aviso ADD COLUMN IF NOT EXISTS activo  BOOLEAN NOT NULL DEFAULT true");
        DB::statement("ALTER TABLE dbo.d2_aviso ADD COLUMN IF NOT EXISTS orden   SMALLINT NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE dbo.d2_aviso ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT NOW()");
        DB::statement("ALTER TABLE dbo.d2_aviso ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW()");

        DB::table('dbo.d2_configuracion')->insertOrIgnore([
            'concepto' => 'AVISOS_DIRECCION',
            'valor'    => 'horizontal',
        ]);
    }

    public function down(): void {}
};
