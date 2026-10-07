<?php

use App\Services\DatosBaseInicialesService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        app(DatosBaseInicialesService::class)->cargar();
    }

    public function down(): void
    {
        throw new RuntimeException('Los datos base pueden estar referenciados o configurados. No se eliminan mediante rollback.');
    }
};
