<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egresos', function (Blueprint $table) {
            if (! Schema::hasColumn('egresos', 'id_activo')) {
                $table->unsignedInteger('id_activo')->nullable()->after('id_sucursal');
            }
            if (! Schema::hasColumn('egresos', 'pendiente_capitalizacion')) {
                $table->boolean('pendiente_capitalizacion')->default(false)->after('id_activo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('egresos', function (Blueprint $table) {
            if (Schema::hasColumn('egresos', 'pendiente_capitalizacion')) {
                $table->dropColumn('pendiente_capitalizacion');
            }
            if (Schema::hasColumn('egresos', 'id_activo')) {
                $table->dropColumn('id_activo');
            }
        });
    }
};
