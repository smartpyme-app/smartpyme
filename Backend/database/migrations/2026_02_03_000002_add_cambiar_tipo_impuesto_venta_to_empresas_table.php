<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCambiarTipoImpuestoVentaToEmpresasTable extends Migration
{
    /**
     * Run the migrations.
     * Permite habilitar en ventas la columna para cambiar tipo de impuesto (gravada/exenta/no sujeta) por detalle.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('cambiar_tipo_impuesto_venta')->default(false)->after('vendedor_detalle_venta');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('cambiar_tipo_impuesto_venta');
        });
    }
}
