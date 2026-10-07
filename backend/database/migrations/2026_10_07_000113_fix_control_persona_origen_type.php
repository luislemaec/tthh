<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        // CHAR(120) agrega espacios a APP/WEB; VARCHAR conserva el límite sin relleno.
        // Se mantienen las filas, los valores NULL y el tamaño máximo existente.
        DB::connection('pgsql')->statement('
            ALTER TABLE dbo.sg_control_persona
            ALTER COLUMN origen TYPE VARCHAR(120)
            USING rtrim(origen::text)
        ');
    }

    public function down(): void
    {
        DB::connection('pgsql')->statement('
            ALTER TABLE dbo.sg_control_persona
            ALTER COLUMN origen TYPE CHAR(120)
            USING origen::character(120)
        ');
    }
};
