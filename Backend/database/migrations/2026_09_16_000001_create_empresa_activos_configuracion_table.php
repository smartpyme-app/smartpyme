<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_activos_configuracion', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_empresa')->unique();
            $table->string('frecuencia', 16)->default('mensual');
            $table->unsignedTinyInteger('dia_corte')->default(1);
            $table->unsignedTinyInteger('redondeo_decimales')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_activos_configuracion');
    }
};
