<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePromocionalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promocionales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->decimal('descuento', 10, 2);
            $table->enum('tipo', ['porcentaje', 'monto_fijo'])->default('porcentaje'); 
            $table->boolean('activo')->default(true);
            $table->string('campania')->nullable();
            $table->text('descripcion')->nullable();
            $table->json('planes_permitidos')->nullable();
            $table->json('opciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('promocionales');
    }
}
