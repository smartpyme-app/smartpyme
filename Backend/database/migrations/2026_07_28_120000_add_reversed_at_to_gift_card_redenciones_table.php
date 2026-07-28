<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReversedAtToGiftCardRedencionesTable extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Schema::table('gift_card_redenciones', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('id_comision_movimiento');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('gift_card_redenciones', function (Blueprint $table) {
            $table->dropColumn('reversed_at');
        });
    }
}
