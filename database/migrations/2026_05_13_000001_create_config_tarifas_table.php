<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('config_tarifas')) {
            return;
        }

        Schema::create('config_tarifas', function (Blueprint $table): void {
            $table->id();
            $table->string('categoria', 80)->default('global');
            $table->string('clave', 100);
            $table->decimal('valor', 15, 2)->default(0);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['categoria', 'clave']);
            $table->index(['categoria', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_tarifas');
    }
};
