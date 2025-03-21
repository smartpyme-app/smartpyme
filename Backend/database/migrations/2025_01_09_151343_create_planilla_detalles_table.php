<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanillaDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('planilla_detalles')) {
        Schema::create('planilla_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_planilla');
            $table->unsignedBigInteger('id_empleado');

            // Ingresos
            $table->decimal('salario_base', 10, 2);
            $table->decimal('salario_devengado', 10, 2)->default(0);
            $table->decimal('dias_laborados', 10, 2)->default(0);
            $table->decimal('horas_extra', 10, 2)->default(0);
            $table->decimal('monto_horas_extra', 10, 2)->default(0);
            $table->decimal('comisiones', 10, 2)->default(0);
            $table->decimal('bonificaciones', 10, 2)->default(0);
            $table->decimal('otros_ingresos', 10, 2)->default(0);

            // Descuentos de ley
            $table->decimal('isss_empleado', 10, 2);
            $table->decimal('isss_patronal', 10, 2);
            $table->decimal('afp_empleado', 10, 2);
            $table->decimal('afp_patronal', 10, 2);
            $table->decimal('renta', 10, 2)->default(0);
            
            // Otros descuentos
            $table->decimal('prestamos', 10, 2)->default(0);
            $table->decimal('anticipos', 10, 2)->default(0);
            $table->decimal('otros_descuentos', 10, 2)->default(0);
            $table->decimal('descuentos_judiciales', 10, 2)->default(0);
            $table->text('detalle_otras_deducciones')->nullable();

            // Totales
            $table->decimal('total_ingresos', 10, 2);
            $table->decimal('total_descuentos', 10, 2);
            $table->decimal('sueldo_neto', 10, 2);

            $table->integer('estado');

            $table->timestamps();

            // Llaves foráneas
            $table->foreign('id_planilla')
                ->references('id')
                ->on('planillas')
                ->onDelete('cascade');

            $table->foreign('id_empleado')
                ->references('id')
                    ->on('empleados')
                    ->onDelete('cascade');
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
        Schema::dropIfExists('planilla_detalles');
    }
}
