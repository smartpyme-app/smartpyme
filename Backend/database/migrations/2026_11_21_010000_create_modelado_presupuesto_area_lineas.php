<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modelado_presupuesto_area_lineas')) {
            Schema::create('modelado_presupuesto_area_lineas', function (Blueprint $table) {
                $table->unsignedInteger('id_empresa');
                $table->unsignedSmallInteger('anio');
                $table->unsignedInteger('id_area_empresa');
                $table->string('rubro', 40);
                $table->decimal('monto_anual', 15, 2)->default(0);
                $table->timestamps();

                $table->primary(['id_empresa', 'anio', 'id_area_empresa', 'rubro'], 'modelado_pres_area_pk');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('modelado_presupuesto_area_lineas');
    }
};
