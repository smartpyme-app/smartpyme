<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CABYS (CR): 13 dígitos en cod_actividad_economica; descripciones oficiales largas en giro.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('cod_actividad_economica', 15)->nullable()->change();
            $table->text('giro')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('cod_actividad_economica', 10)->nullable()->change();
            $table->string('giro', 255)->nullable()->change();
        });
    }
};
