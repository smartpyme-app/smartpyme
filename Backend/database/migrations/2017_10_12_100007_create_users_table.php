<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{

    // public function up()
    // {
    //     Schema::create('users', function (Blueprint $table) {
    //         $table->increments('id');
            
    //         $table->string('name');
    //         $table->string('email')->unique()->nullable();
    //         $table->string('username')->unique();
    //         $table->string('password');
    //         $table->string('tipo');
    //         $table->string('avatar')->default('usuarios/default.jpg');
    //         $table->string('codigo')->default(1);
    //         $table->boolean('activo')->default(1);

    //         $table->integer('caja_id')->nullable();
    //         $table->integer('empleado_id')->nullable();
    //         $table->integer('sucursal_id')->nullable();
    //         $table->integer('bodega_id')->nullable();

    //         $table->timestamp('ultimo_login')->nullable();
    //         $table->timestamp('ultimo_logout')->nullable();

    //         $table->softDeletes();
    //         $table->rememberToken();
    //         $table->timestamps();
    //     });
    // }
    
    // public function down()
    // {
    //     Schema::dropIfExists('users');
    // }
}
