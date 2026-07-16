<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dbo.nom_he_registro ADD COLUMN IF NOT EXISTS devuelto_count SMALLINT NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dbo.nom_he_registro DROP COLUMN IF EXISTS devuelto_count');
    }
};
