<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNuevosCamposSuscripcionesToEmpresaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('frecuencia_pago')->nullable()->after('total');
            $table->decimal('monto_mensual', 10, 2)->nullable()->after('frecuencia_pago');
            $table->decimal('monto_anual', 10, 2)->nullable()->after('monto_mensual');
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
            $table->dropColumn('frecuencia_pago');
            $table->dropColumn('monto_mensual');
            $table->dropColumn('monto_anual');
        });
    }
}
