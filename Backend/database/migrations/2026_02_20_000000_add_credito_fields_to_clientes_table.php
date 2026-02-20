<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreditoFieldsToClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('habilita_credito')->default(false)->after('id_vendedor');
            $table->unsignedSmallInteger('dias_credito')->nullable()->after('habilita_credito');
            $table->decimal('limite_credito', 12, 2)->nullable()->after('dias_credito');
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
            $table->dropColumn(['habilita_credito', 'dias_credito', 'limite_credito']);
        });
    }
}
