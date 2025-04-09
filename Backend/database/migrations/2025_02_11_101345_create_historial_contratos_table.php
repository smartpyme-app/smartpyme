<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistorialContratosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('historial_contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_empleado')->constrained('empleados')->onDelete('cascade');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->integer('tipo_contrato'); // ['Permanente', 'Temporal', 'Por obra']);
            $table->decimal('salario', 10, 2);
            $table->foreignId('id_cargo')->constrained('cargos_de_empresa');
            $table->string('motivo_cambio')->nullable();
            $table->integer('documento_respaldo')->nullable();
            $table->integer('estado')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_empleado', 'fecha_inicio']);
            $table->index('tipo_contrato');
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
        Schema::dropIfExists('historial_contratos');
    }
}
