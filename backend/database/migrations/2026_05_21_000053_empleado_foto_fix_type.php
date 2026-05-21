<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('pgsql')->statement(
            'ALTER TABLE dbo.ad_empleado ALTER COLUMN foto TYPE varchar(500) USING foto::text'
        );
    }

    public function down(): void {}
};
