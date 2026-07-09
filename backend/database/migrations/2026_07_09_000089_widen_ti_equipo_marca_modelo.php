<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.ti_equipo ALTER COLUMN marca TYPE VARCHAR(150)');
        DB::statement('ALTER TABLE dbo.ti_equipo ALTER COLUMN modelo TYPE VARCHAR(300)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.ti_equipo ALTER COLUMN marca TYPE VARCHAR(50)');
        DB::statement('ALTER TABLE dbo.ti_equipo ALTER COLUMN modelo TYPE VARCHAR(50)');
    }
};
