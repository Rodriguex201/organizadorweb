<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacion_extraccion_logs', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('fecha');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario', 150)->nullable();
            $table->unsignedInteger('cantidad_registros')->default(0);
            $table->longText('archivo_origen')->nullable();
            $table->longText('errores_encontrados')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_extraccion_logs');
    }
};
