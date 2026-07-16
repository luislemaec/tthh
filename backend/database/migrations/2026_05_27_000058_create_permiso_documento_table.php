<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dbo.d2_permiso_documento', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('permiso_id');
            $table->string('tipo_doc', 60);
            $table->string('nombre_archivo', 200);
            $table->string('alfresco_id', 200);
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dbo.d2_permiso_documento');
    }
};
