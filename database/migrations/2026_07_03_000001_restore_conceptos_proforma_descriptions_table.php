<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONCEPTOS_PROFORMA = [
        ['codigo' => '0010', 'nombre' => 'SERVICIO CLOUD ARRENDAMIENTO / ACTUALIZACION SOFTWARE (SAAS)', 'cuenta' => null, 'activo' => true],
        ['codigo' => '0011', 'nombre' => 'SERVICIO CLOUD TERMINALES EXTRA', 'cuenta' => null, 'activo' => true],
        ['codigo' => '0099', 'nombre' => 'SERVICIO CLOUD NOMINA ELECTRONICA', 'cuenta' => null, 'activo' => true],
        ['codigo' => '0081', 'nombre' => 'SERVICIO CLOUD FACTURACION ELECTRONICA', 'cuenta' => null, 'activo' => true],
        ['codigo' => '0101', 'nombre' => 'SERVICIO CLOUD RECEPCION COMPRAS', 'cuenta' => null, 'activo' => true],
        ['codigo' => '0102', 'nombre' => 'SERVICIO CLOUD SOPORTE ELECTRONICO', 'cuenta' => null, 'activo' => true],
        ['codigo' => 'EXTRA', 'nombre' => 'CARGO EXTRA MANUAL', 'cuenta' => null, 'activo' => true],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('conceptos')) {
            Schema::create('conceptos', function (Blueprint $table): void {
                $table->id();
                $table->string('codigo', 10)->unique();
                $table->string('nombre', 150);
                $table->string('cuenta', 30)->nullable();
                $table->boolean('activo')->default(true);
            });
        }

        foreach (self::CONCEPTOS_PROFORMA as $concepto) {
            DB::table('conceptos')->updateOrInsert(
                ['codigo' => $concepto['codigo']],
                [
                    'nombre' => $concepto['nombre'],
                    'cuenta' => $concepto['cuenta'],
                    'activo' => $concepto['activo'],
                ],
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('conceptos')) {
            return;
        }

        DB::table('conceptos')
            ->whereIn('codigo', array_column(self::CONCEPTOS_PROFORMA, 'codigo'))
            ->delete();
    }
};
