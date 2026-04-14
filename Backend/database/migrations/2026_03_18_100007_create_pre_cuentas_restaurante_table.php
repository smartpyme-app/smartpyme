<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePreCuentasRestauranteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pre_cuentas_restaurante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('restaurante_sesiones_mesa')->onDelete('cascade');
            $table->foreignId('division_cuenta_id')->nullable()->constrained('division_cuenta_restaurante')->onDelete('cascade');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['pendiente', 'facturada', 'anulada'])->default('pendiente');
            $table->unsignedInteger('factura_id')->nullable()->comment('ID de venta cuando se convierte en factura');
            $table->string('numero_pre_cuenta', 30)->nullable();
            $table->timestamps();

            $table->index(['sesion_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pre_cuentas_restaurante');
    }
}
