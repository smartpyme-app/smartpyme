<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComisionesEmpleadosTable extends Migration
{
    /**
     * Ledger de comisiones (independiente de totales de ventas y planilla).
     * Se asocia al vendedor (usuario), no al empleado de planilla.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('comisiones_empleados')) {
            Schema::create('comisiones_empleados', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_vendedor');
                $table->unsignedInteger('id_empresa');

                /** venta | manual | canje_tarjeta */
                $table->string('origen', 40)->index();

                $table->string('correlativo_referencia')->nullable()->index();
                $table->unsignedInteger('id_venta')->nullable();

                $table->string('categoria')->nullable();
                $table->decimal('base_calculo', 12, 2);
                $table->decimal('tasa_comision', 8, 4);
                $table->decimal('monto_comision', 12, 2);
                $table->date('fecha');
                $table->text('notas')->nullable();

                $table->timestamps();

                $table->foreign('id_vendedor')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');

                $table->foreign('id_empresa')
                    ->references('id')
                    ->on('empresas')
                    ->onDelete('cascade');

                $table->foreign('id_venta')
                    ->references('id')
                    ->on('ventas')
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
        Schema::dropIfExists('comisiones_empleados');
    }
}
