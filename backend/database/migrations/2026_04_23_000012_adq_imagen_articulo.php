<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE adq.articulo ADD COLUMN IF NOT EXISTS imagen VARCHAR(200) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE adq.articulo DROP COLUMN IF EXISTS imagen');
    }
};
