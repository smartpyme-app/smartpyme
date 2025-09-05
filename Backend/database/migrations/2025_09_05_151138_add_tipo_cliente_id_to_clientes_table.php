<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipoClienteIdToClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->integer('nivel_cliente')->default(1)->after('id_empresa')->comment('DEFAULT 1 (STANDARD)')
            ->comment('NULL usa nivel_cliente');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['id_tipo_cliente']);
            $table->dropColumn('id_tipo_cliente');
        });
    }
}
