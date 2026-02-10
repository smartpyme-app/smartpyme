<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAguinaldoDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('aguinaldo_detalles')) {
            Schema::create('aguinaldo_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_aguinaldo');
                $table->unsignedBigInteger('id_empleado');

                // Montos de aguinaldo
                $table->decimal('monto_aguinaldo_bruto', 10, 2);
                $table->decimal('monto_exento', 10, 2)->default(0);
                $table->decimal('monto_gravado', 10, 2)->default(0);
                $table->decimal('retencion_renta', 10, 2)->default(0);
                $table->decimal('aguinaldo_neto', 10, 2)->default(0);

                $table->foreign('id_aguinaldo')->references('id')->on('aguinaldos')->onDelete('cascade');
                $table->foreign('id_empleado')->references('id')->on('empleados')->onDelete('cascade');
                // Información adicional
                $table->text('notas')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Índices
                $table->index('id_aguinaldo');
                $table->index('id_empleado');
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
        Schema::dropIfExists('aguinaldo_detalles');
    }
}
