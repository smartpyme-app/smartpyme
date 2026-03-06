<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrestamoMovimientosTable extends Migration
{
    /**
     * Run the migrations.
     * Movimientos de cada préstamo: desembolso inicial y abonos (planilla o efectivo).
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('prestamo_movimientos')) {
            Schema::create('prestamo_movimientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_prestamo');

                /** desembolso | abono_planilla | abono_efectivo */
                $table->string('tipo', 30);

                /** Monto: positivo en desembolso; en abonos es el valor del abono */
                $table->decimal('monto', 12, 2);

                /** Saldo del préstamo después de este movimiento */
                $table->decimal('saldo_despues', 12, 2);

                $table->string('descripcion', 500)->nullable();
                $table->date('fecha');

                /** Solo para tipo abono_planilla: detalle de planilla que generó el descuento */
                $table->unsignedBigInteger('id_planilla_detalle')->nullable();

                $table->timestamps();

                $table->foreign('id_prestamo')
                    ->references('id')
                    ->on('prestamos_empleados')
                    ->onDelete('cascade');

                $table->foreign('id_planilla_detalle')
                    ->references('id')
                    ->on('planilla_detalles')
                    ->onDelete('set null');
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
        Schema::dropIfExists('prestamo_movimientos');
    }
}
