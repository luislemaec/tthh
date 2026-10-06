<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // dbo.supervisor_area tenía UNIQUE (id_depto): un solo supervisor por departamento.
    // Se reemplaza por UNIQUE (id_depto, id_supervisor) para permitir 2 supervisores en las
    // áreas sin padre (ej. PRESIDENCIA) — la regla "solo áreas sin padre, máximo 2" la aplica
    // SupervisorController::store(), no la base de datos. No toca filas existentes.
    // DROP CONSTRAINT elimina también el índice único asociado.
    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.supervisor_area DROP CONSTRAINT IF EXISTS uq_supervisor_area");
        DB::statement("ALTER TABLE dbo.supervisor_area DROP CONSTRAINT IF EXISTS uq_supervisor_area_depto_sup");
        DB::statement("ALTER TABLE dbo.supervisor_area ADD CONSTRAINT uq_supervisor_area_depto_sup UNIQUE (id_depto, id_supervisor)");
    }

    // OJO: falla si ya hay áreas con 2 supervisores — hay que dejar uno por área antes de revertir.
    public function down(): void
    {
        DB::statement("ALTER TABLE dbo.supervisor_area DROP CONSTRAINT IF EXISTS uq_supervisor_area_depto_sup");
        DB::statement("ALTER TABLE dbo.supervisor_area ADD CONSTRAINT uq_supervisor_area UNIQUE (id_depto)");
    }
};
