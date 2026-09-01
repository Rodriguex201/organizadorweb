<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sg_proform') || Schema::hasColumn('sg_proform', 'comprobante_pago')) {
            return;
        }

        Schema::table('sg_proform', function (Blueprint $table): void {
            $table->string('comprobante_pago', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sg_proform') || !Schema::hasColumn('sg_proform', 'comprobante_pago')) {
            return;
        }

        Schema::table('sg_proform', function (Blueprint $table): void {
            $table->dropColumn('comprobante_pago');
        });
    }
};
