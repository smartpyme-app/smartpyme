<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccesoTemporalHastaToSuscripcionesTable extends Migration
{
    /**
     * Acceso concedido por admin sin cambiar fecha_proximo_pago (extiende uso de la plataforma hasta esta fecha).
     */
    public function up()
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->timestamp('acceso_temporal_hasta')->nullable()->after('fecha_proximo_pago');
        });
    }

    public function down()
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->dropColumn('acceso_temporal_hasta');
        });
    }
}
