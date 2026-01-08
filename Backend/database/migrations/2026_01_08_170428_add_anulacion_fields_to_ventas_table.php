<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAnulacionFieldsToVentasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->date('fecha_anulacion')->nullable()->after('fecha_pago');
            $table->integer('tipo_anulacion')->nullable()->after('fecha_anulacion');
            $table->text('motivo_anulacion')->nullable()->after('tipo_anulacion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['fecha_anulacion', 'tipo_anulacion', 'motivo_anulacion']);
        });
    }
}
