<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 10, 2);
            $table->integer('duracion_dias')->default(30);
            $table->boolean('activo')->default(true);
            $table->string('enlace_n1co')->nullable();
            $table->json('caracteristicas')->nullable();
            
            // Campos específicos para n1co
            $table->string('id_enlace_pago_n1co')->nullable();
            $table->json('n1co_metadata')->nullable();
            
            // Campos de control del plan
            $table->boolean('permite_periodo_prueba')->default(false);
            $table->integer('dias_periodo_prueba')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('planes');
    }
}
