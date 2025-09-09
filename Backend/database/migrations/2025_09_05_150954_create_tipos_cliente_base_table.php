<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTiposClienteBaseTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tipos_cliente_base', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('STANDARD/VIP/ULTRAVIP');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->integer('orden')->comment('1=STANDARD, 2=VIP, 3=ULTRAVIP');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tipos_cliente_base');
    }
}
