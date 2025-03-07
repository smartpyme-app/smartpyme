<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIaMetricaMensualEmpresasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ia_metricas_mensuales_empresas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->date('fecha');
            $table->decimal('ventas_sin_iva', 15, 2)->default(0);
            $table->decimal('ventas_con_iva', 15, 2)->default(0);
            $table->decimal('egresos_sin_iva', 15, 2)->default(0);
            $table->decimal('egresos_con_iva', 15, 2)->default(0);
            $table->decimal('costo_venta_sin_iva', 15, 2)->default(0);
            $table->decimal('flujo_efectivo_sin_iva', 15, 2)->default(0);
            $table->decimal('flujo_efectivo_con_iva', 15, 2)->default(0);
            $table->decimal('rentabilidad_monto', 15, 2)->default(0);
            $table->decimal('rentabilidad_porcentaje', 6, 2)->default(0);
            $table->decimal('cxc_totales', 15, 2)->default(0);
            $table->decimal('cxc_vencidas', 15, 2)->default(0);
            $table->decimal('cxc_vencimiento_30_dias', 15, 2)->default(0);
            $table->decimal('cxp_totales', 15, 2)->default(0);
            $table->decimal('cxp_vencidas', 15, 2)->default(0);
            $table->decimal('cxp_vencimiento_30_dias', 15, 2)->default(0);
            $table->decimal('ventas_vs_mes_anterior', 6, 2)->default(0);
            $table->decimal('egresos_vs_mes_anterior', 6, 2)->default(0);
            $table->decimal('flujo_efectivo_vs_mes_anterior', 6, 2)->default(0);
            $table->decimal('rentabilidad_vs_mes_anterior', 6, 2)->default(0);
            $table->decimal('ventas_vs_presupuesto', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['id_empresa', 'fecha']);
            $table->index('fecha');
            $table->index('id_empresa');
            
            // Relación con la tabla empresas
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ia_metricas_mensuales_empresas');
    }
}
