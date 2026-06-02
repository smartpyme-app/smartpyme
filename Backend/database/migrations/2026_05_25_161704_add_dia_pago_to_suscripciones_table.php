<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AddDiaPagoToSuscripcionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('suscripciones', function (Blueprint $table) {
            $table->integer('dia_pago')->nullable()->after('fecha_proximo_pago');
        });

        // Obtener el día de pago según la fecha_proximo_pago de todas las suscripciones
        DB::table('suscripciones')
            ->whereNotNull('fecha_proximo_pago')
            ->chunkById(100, function ($suscripciones) {
                foreach ($suscripciones as $sus) {
                    $fecha = Carbon::parse($sus->fecha_proximo_pago);
                    DB::table('suscripciones')
                        ->where('id', $sus->id)
                        ->update(['dia_pago' => $fecha->day]);
                }
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
            $table->dropColumn('dia_pago');
        });
    }
}
