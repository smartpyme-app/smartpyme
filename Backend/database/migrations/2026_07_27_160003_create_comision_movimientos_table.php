<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComisionMovimientosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comision_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_vendedor');
            $table->unsignedBigInteger('id_periodo')->nullable();
            $table->string('origen', 32); // venta|redencion_gift_card|ajuste_devolucion
            $table->unsignedBigInteger('id_venta')->nullable();
            $table->unsignedBigInteger('id_detalle_venta')->nullable();
            $table->unsignedBigInteger('id_gift_card_redencion')->nullable();
            $table->unsignedBigInteger('id_categoria')->nullable();
            $table->unsignedBigInteger('id_subcategoria')->nullable();
            $table->decimal('monto_base', 14, 4);
            $table->decimal('porcentaje_aplicado', 8, 4);
            $table->decimal('monto_comision', 14, 4);
            $table->unsignedBigInteger('id_movimiento_origen')->nullable();
            $table->timestamp('fecha_evento')->nullable();
            $table->timestamps();

            // Idempotencia venta/redención: servicio con firstOrCreate (MySQL no soporta unique parcial).
            $table->index(
                ['id_empresa', 'origen', 'id_detalle_venta'],
                'comision_mov_idx_detalle'
            );

            $table->unique(
                ['id_empresa', 'id_gift_card_redencion'],
                'comision_mov_unique_redencion'
            );

            $table->unique(
                ['id_empresa', 'origen', 'id_movimiento_origen'],
                'comision_mov_unique_ajuste'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('comision_movimientos');
    }
}
