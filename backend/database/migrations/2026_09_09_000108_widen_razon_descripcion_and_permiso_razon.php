<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // dbo.d2_razon.descripcion venía en VARCHAR(30) (schema legado, sin migración propia) —
        // validado igual en RazonController (max:30). TH está cargando plantillas de razones con
        // nombres más largos (ej. "CALAMIDAD DOMESTICA JUSTIFICADA", 32 caracteres) y quedaban
        // truncadas (una fila real ya guardada como "CALAMIDAD DOMESTICA JUSTIFICAD", sin la A
        // final). Se amplía a VARCHAR(100).
        DB::statement('ALTER TABLE dbo.d2_razon ALTER COLUMN descripcion TYPE VARCHAR(100)');

        // dbo.d2_permiso.razon guarda una copia de razon.descripcion al momento de crear el
        // permiso (PermisosController::store(), trim($razon->descripcion)) — si esta columna se
        // queda más angosta que la de origen, la copia se trunca igual aunque el catálogo ya
        // permita el texto largo. Se ensancha en la misma medida.
        DB::statement('ALTER TABLE dbo.d2_permiso ALTER COLUMN razon TYPE VARCHAR(100)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.d2_permiso ALTER COLUMN razon TYPE VARCHAR(30)');
        DB::statement('ALTER TABLE dbo.d2_razon ALTER COLUMN descripcion TYPE VARCHAR(30)');
    }
};
