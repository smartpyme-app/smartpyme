<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransaccionesPuntosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transacciones_puntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_cliente')->constrained('clientes');
            $table->unsignedInteger('id_empresa')->constrained('empresas');
            $table->unsignedInteger('id_venta')->nullable()->constrained('ventas');
            $table->enum('tipo', ['ganancia', 'canje', 'ajuste', 'expiracion']);
            $table->double('puntos')->comment('+ ganancia, - canje/exp');
            $table->double('puntos_antes');
            $table->double('puntos_despues');
            $table->decimal('monto_asociado', 10, 2)->nullable();
            $table->integer('puntos_consumidos')->default(0);
            $table->text('descripcion')->nullable();
            $table->date('fecha_expiracion')->nullable()->comment('solo ganancia');
            $table->string('idempotency_key')->unique();
            $table->integer('venta_ganancia_key')->storedAs('CASE WHEN tipo = \'ganancia\' AND id_venta IS NOT NULL THEN id_venta ELSE NULL END');
            $table->timestamps();
            
            // Constraint: una ganancia por venta
            $table->unique(['id_venta', 'venta_ganancia_key'], 'venta_ganancia_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transacciones_puntos');
    }
}
