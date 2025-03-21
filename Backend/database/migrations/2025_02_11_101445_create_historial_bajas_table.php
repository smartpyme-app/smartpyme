<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistorialBajasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('historial_bajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_empleado')->constrained('empleados');
            $table->date('fecha_baja');
            //$table->enum('tipo_baja', ['Renuncia', 'Despido', 'Terminación de contrato', 'Otro']);
            $table->integer('tipo_baja');
            $table->text('motivo');
            $table->integer('documento_respaldo')->nullable();
            $table->integer('estado')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_empleado');
            $table->index('tipo_baja');
            $table->index('documento_respaldo');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('historial_bajas');
    }
}
