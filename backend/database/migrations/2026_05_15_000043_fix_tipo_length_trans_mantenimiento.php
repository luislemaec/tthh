<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE dbo.trans_mantenimiento
                ALTER COLUMN tipo TYPE VARCHAR(30)
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE dbo.trans_mantenimiento
                ALTER COLUMN tipo TYPE VARCHAR(15)
        ");
    }
};
