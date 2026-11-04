<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modelado_presupuesto_sucursal_lineas', function (Blueprint $table) {
            $table->unsignedInteger('id_empresa');
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('id_sucursal');
            $table->string('rubro', 40);
            $table->decimal('monto_anual', 15, 2)->default(0);
            $table->timestamps();

            $table->primary(['id_empresa', 'anio', 'id_sucursal', 'rubro'], 'modelado_pres_suc_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelado_presupuesto_sucursal_lineas');
    }
};
