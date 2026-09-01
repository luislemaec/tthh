<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Corrige el default de 'activo' a false — el diseño real es que un dispositivo nuevo
    // requiere activación manual del admin (ver ZktecoController::registrarContacto()).
    // No toca filas existentes, solo el default para futuros inserts sin valor explícito.
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.d2_zkteco_dispositivo ALTER COLUMN activo SET DEFAULT false");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.d2_zkteco_dispositivo ALTER COLUMN activo SET DEFAULT true");
    }
};
