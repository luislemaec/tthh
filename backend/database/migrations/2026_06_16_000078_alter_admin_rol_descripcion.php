<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Obtener la definición actual de la vista antes de borrarla
        $viewDef = DB::selectOne("SELECT pg_get_viewdef('dbo.view_usuario_opciones'::regclass, true) AS def")?->def;

        DB::statement('DROP VIEW IF EXISTS dbo.view_usuario_opciones');
        DB::statement('ALTER TABLE dbo.admin_rol ALTER COLUMN descripcion TYPE VARCHAR(50)');

        if ($viewDef) {
            DB::statement("CREATE VIEW dbo.view_usuario_opciones AS {$viewDef}");
        }
    }

    public function down(): void
    {
        $viewDef = DB::selectOne("SELECT pg_get_viewdef('dbo.view_usuario_opciones'::regclass, true) AS def")?->def;

        DB::statement('DROP VIEW IF EXISTS dbo.view_usuario_opciones');
        DB::statement('ALTER TABLE dbo.admin_rol ALTER COLUMN descripcion TYPE VARCHAR(20)');

        if ($viewDef) {
            DB::statement("CREATE VIEW dbo.view_usuario_opciones AS {$viewDef}");
        }
    }
};
