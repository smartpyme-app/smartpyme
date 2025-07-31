<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDatosBancariosToEmpleadosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empleados', function (Blueprint $table) {
                $table->string('banco')->nullable()->after('email');
                $table->string('tipo_cuenta')->nullable()->after('banco');
                $table->string('numero_cuenta')->nullable()->after('tipo_cuenta');
                $table->string('titular_cuenta')->nullable()->after('numero_cuenta');
                $table->string('forma_pago')->nullable()->after('titular_cuenta');
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('banco');
            $table->dropColumn('tipo_cuenta');
            $table->dropColumn('numero_cuenta');
            $table->dropColumn('titular_cuenta');
            $table->dropColumn('forma_pago');
        });
    }
}
