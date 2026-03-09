<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sg_proform')) {
            return;
        }

        Schema::table('sg_proform', function (Blueprint $table) {
            if (!Schema::hasColumn('sg_proform', 'enviado')) {
                $table->unsignedTinyInteger('enviado')->default(0)->after('estado');
            }

            if (!Schema::hasColumn('sg_proform', 'fecha_envio')) {
                $table->dateTime('fecha_envio')->nullable()->after('enviado');
            }

            if (!Schema::hasColumn('sg_proform', 'intentos_envio')) {
                $table->unsignedInteger('intentos_envio')->default(0)->after('fecha_envio');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sg_proform')) {
            return;
        }

        Schema::table('sg_proform', function (Blueprint $table) {
            if (Schema::hasColumn('sg_proform', 'intentos_envio')) {
                $table->dropColumn('intentos_envio');
            }

            if (Schema::hasColumn('sg_proform', 'fecha_envio')) {
                $table->dropColumn('fecha_envio');
            }

            if (Schema::hasColumn('sg_proform', 'enviado')) {
                $table->dropColumn('enviado');
            }
        });
    }
};
