<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sg_proform') || Schema::hasColumn('sg_proform', 'id_cobro')) {
            return;
        }

        Schema::table('sg_proform', function (Blueprint $table) {
            $table->integer('id_cobro')->nullable()->after('id');
            $table->index('id_cobro', 'sg_proform_id_cobro_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sg_proform') || !Schema::hasColumn('sg_proform', 'id_cobro')) {
            return;
        }

        Schema::table('sg_proform', function (Blueprint $table) {
            $table->dropIndex('sg_proform_id_cobro_index');
            $table->dropColumn('id_cobro');
        });
    }
};
