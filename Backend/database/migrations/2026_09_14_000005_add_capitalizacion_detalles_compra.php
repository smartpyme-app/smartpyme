<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalles_compra', function (Blueprint $table) {
            if (! Schema::hasColumn('detalles_compra', 'id_activo')) {
                $table->unsignedInteger('id_activo')->nullable()->after('id_compra');
            }
            if (! Schema::hasColumn('detalles_compra', 'es_activo_fijo')) {
                $table->boolean('es_activo_fijo')->default(false)->after('id_activo');
            }
            if (! Schema::hasColumn('detalles_compra', 'pendiente_capitalizacion')) {
                $table->boolean('pendiente_capitalizacion')->default(false)->after('es_activo_fijo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detalles_compra', function (Blueprint $table) {
            if (Schema::hasColumn('detalles_compra', 'pendiente_capitalizacion')) {
                $table->dropColumn('pendiente_capitalizacion');
            }
            if (Schema::hasColumn('detalles_compra', 'es_activo_fijo')) {
                $table->dropColumn('es_activo_fijo');
            }
            if (Schema::hasColumn('detalles_compra', 'id_activo')) {
                $table->dropColumn('id_activo');
            }
        });
    }
};
