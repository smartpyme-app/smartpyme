<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipoPagoToOrdenesPagoTable extends Migration
{

    public function up()
    {
        Schema::table('ordenes_pago', function (Blueprint $table) {
            $table->string('tipo_pago')->after('divisa')->nullable();
        });
    }

    public function down()
    {
        Schema::table('ordenes_pago', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_pago'
            ]);
        });
    }
}