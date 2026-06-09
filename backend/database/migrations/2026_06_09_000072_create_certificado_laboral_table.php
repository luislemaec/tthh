<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dbo.d2_certificado_laboral', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 25);
            $table->string('id_emp', 20);
            $table->date('fecha_emision');
            $table->string('alfresco_id', 100)->nullable();
            $table->string('nombre_archivo', 200)->nullable();
            $table->string('usuario_emision', 20);
            $table->timestamps();

            $table->unique('numero');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dbo.d2_certificado_laboral');
    }
};
