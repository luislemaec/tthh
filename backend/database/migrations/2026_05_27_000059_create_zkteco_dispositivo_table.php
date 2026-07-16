<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dbo.d2_zkteco_dispositivo', function (Blueprint $table) {
            $table->increments('id');
            $table->string('serial', 50)->unique();
            $table->string('nombre', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('ultimo_push')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dbo.d2_zkteco_dispositivo');
    }
};
