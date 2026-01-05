<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddConfiguracionDescuentosToEmpleadosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->json('configuracion_descuentos')->nullable()->after('afp');
        });

        // Establecer valores por defecto para registros existentes
        DB::table('empleados')->whereNull('configuracion_descuentos')->update([
            'configuracion_descuentos' => json_encode([
                'aplicar_afp' => true,
                'aplicar_isss' => true
            ])
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('configuracion_descuentos');
        });
    }
}
