<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modelado_clasificaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('clave', 120);
            $table->string('nombre', 200);
            $table->string('rubro', 40);
            $table->string('tipo', 20)->nullable();
            $table->timestamps();

            $table->unique(['id_empresa', 'clave']);
        });

        Schema::create('modelado_calendario', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('clave', 120)->nullable();
            $table->string('nombre', 200);
            $table->string('detalle', 200)->nullable();
            $table->decimal('monto', 15, 2);
            $table->string('frecuencia', 20);
            $table->string('dia', 32);
            $table->string('origen', 20)->default('manual');
            $table->timestamps();

            $table->index(['id_empresa', 'clave']);
        });

        Schema::create('modelado_presupuesto_repartos', function (Blueprint $table) {
            $table->unsignedInteger('id_empresa');
            $table->unsignedSmallInteger('anio');
            $table->string('reparto', 20);
            $table->timestamps();

            $table->primary(['id_empresa', 'anio']);
        });

        Schema::create('modelado_presupuesto_lineas', function (Blueprint $table) {
            $table->unsignedInteger('id_empresa');
            $table->unsignedSmallInteger('anio');
            $table->string('rubro', 40);
            $table->decimal('monto_anual', 15, 2);
            $table->timestamps();

            $table->primary(['id_empresa', 'anio', 'rubro']);
        });

        Schema::create('modelado_estacionalidad', function (Blueprint $table) {
            $table->unsignedInteger('id_empresa');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->decimal('peso', 10, 4);
            $table->timestamps();

            $table->primary(['id_empresa', 'anio', 'mes']);
        });

        Schema::create('modelado_pronosticos', function (Blueprint $table) {
            $table->unsignedInteger('id_empresa')->primary();
            $table->decimal('efectivo', 15, 2)->nullable();
            $table->date('efectivo_fecha')->nullable();
            $table->decimal('colchon', 15, 2)->nullable();
            $table->decimal('costo_capital_pct', 8, 4)->nullable();
            $table->decimal('escenario_conservador_pct', 8, 2)->default(-10);
            $table->decimal('escenario_optimista_pct', 8, 2)->default(12);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelado_pronosticos');
        Schema::dropIfExists('modelado_estacionalidad');
        Schema::dropIfExists('modelado_presupuesto_lineas');
        Schema::dropIfExists('modelado_presupuesto_repartos');
        Schema::dropIfExists('modelado_calendario');
        Schema::dropIfExists('modelado_clasificaciones');
    }
};
