<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_estados_proforma', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('estado_codigo')->unique();
            $table->string('estado_nombre', 100);
            $table->string('color_fondo', 20);
            $table->string('color_texto', 20);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::table('configuracion_estados_proforma')->insert([
            ['estado_codigo' => 2, 'estado_nombre' => 'Generada', 'color_fondo' => '#DBEAFE', 'color_texto' => '#1D4ED8', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['estado_codigo' => 3, 'estado_nombre' => 'Enviada', 'color_fondo' => '#E0E7FF', 'color_texto' => '#3730A3', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['estado_codigo' => 4, 'estado_nombre' => 'Pagada', 'color_fondo' => '#D1FAE5', 'color_texto' => '#047857', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['estado_codigo' => 6, 'estado_nombre' => 'Facturada', 'color_fondo' => '#F3E8FF', 'color_texto' => '#7E22CE', 'activo' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_estados_proforma');
    }
};
