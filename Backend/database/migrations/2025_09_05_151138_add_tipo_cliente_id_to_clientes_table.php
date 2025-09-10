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
            $table->integer('nivel')->default(1)->after('id_empresa')->comment('DEFAULT 1 (STANDARD)');
            $table->unsignedBigInteger('id_tipo_cliente')->nullable()->after('nivel');
            $table->foreign('id_tipo_cliente')->references('id')->on('tipos_cliente_empresa')->onDelete('set null');
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
            $table->dropForeign(['nivel']);
            $table->dropForeign(['id_tipo_cliente']);
            $table->dropColumn('id_tipo_cliente');
            $table->dropColumn('nivel');
        });
    }
}
