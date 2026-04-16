<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('configuracion_directorio')) {
            return;
        }

        Schema::create('configuracion_directorio', function (Blueprint $table): void {
            $table->id();
            $table->string('ruta_clientes', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_directorio');
    }
};
