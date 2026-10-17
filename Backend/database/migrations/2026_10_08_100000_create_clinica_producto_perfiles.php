<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_producto_perfiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_producto');
            $table->string('via_habitual', 80)->nullable();
            $table->string('unidad_dosis', 80)->nullable();
            $table->boolean('controla_lote')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['id_empresa', 'id_producto'], 'clinica_prod_perfil_emp_prod_uq');
            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_producto')->references('id')->on('productos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_producto_perfiles');
    }
};
