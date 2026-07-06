<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE dbo.d2_razon
            SET descripcion = 'Entrada - Trámite personal',
                updated_at  = NOW()
            WHERE TRIM(descripcion) ILIKE '%Atraso a la entrada%'
              AND descontable = 'SI'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE dbo.d2_razon
            SET descripcion = 'Entrada - Atraso a la entrada',
                updated_at  = NOW()
            WHERE TRIM(descripcion) ILIKE '%Trámite personal%'
              AND descontable = 'SI'
        ");
    }
};
