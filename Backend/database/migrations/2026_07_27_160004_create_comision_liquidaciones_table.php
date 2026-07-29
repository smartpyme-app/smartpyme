<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComisionLiquidacionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comision_liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_periodo');
            $table->unsignedBigInteger('id_vendedor');
            $table->decimal('total_comision', 14, 4)->default(0);
            $table->timestamp('pagado_at')->nullable();
            $table->timestamps();
            $table->unique(['id_empresa', 'id_periodo', 'id_vendedor']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('comision_liquidaciones');
    }
}
