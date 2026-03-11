<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrestamosEmpleadosTable extends Migration
{
    /**
     * Run the migrations.
     * Préstamos a empleados: cabecera por cada préstamo (desembolso inicial).
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('prestamos_empleados')) {
            Schema::create('prestamos_empleados', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_empleado');
                $table->unsignedInteger('id_empresa');

                /** Número correlativo del préstamo por empleado (préstamo #1, #2, ...) */
                $table->unsignedInteger('numero_prestamo');

                $table->decimal('monto_inicial', 12, 2);
                $table->decimal('saldo_actual', 12, 2);

                $table->string('descripcion', 500)->nullable();
                $table->date('fecha_desembolso');

                /** activo | liquidado | cancelado */
                $table->string('estado', 20)->default('activo');

                $table->timestamps();

                $table->foreign('id_empleado')
                    ->references('id')
                    ->on('empleados')
                    ->onDelete('cascade');

                $table->foreign('id_empresa')
                    ->references('id')
                    ->on('empresas')
                    ->onDelete('cascade');

                $table->unique(['id_empleado', 'numero_prestamo']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('prestamos_empleados');
    }
}
