<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_banners', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('imagen_desktop');
            $table->string('imagen_mobile')->nullable();
            $table->json('paises');
            $table->string('url_destino', 2048)->nullable();
            $table->boolean('abrir_nueva_pestana')->default(true);
            $table->unsignedSmallInteger('segundos_visualizacion')->default(5);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->dateTime('publicado_desde')->nullable();
            $table->dateTime('publicado_hasta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_banners');
    }
};