<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdClienteToSuscripcionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->integer('id_cliente')->nullable()->after('empresa_id');

            // Definimos la llave foránea
            $table->foreign('id_cliente')
                ->references('id')
                ->on('clientes')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->dropForeign(['id_cliente']);
            $table->dropColumn('id_cliente');
        });
    }
}
