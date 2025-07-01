<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresaActivosTable extends Migration
{

    // public function up()
    // {
    //     Schema::create('empresa_activos', function (Blueprint $table) {
    //         $table->increments('id');
            
    //         $table->string('nombre');
    //         $table->string('referencia')->nullable();
    //         $table->date('fecha_compra');
    //         $table->date('fecha_retiro')->nullable();
    //         $table->string('estado');//En uso, Desechado, En reparación;
    //         $table->integer('categoria_id');
    //         $table->string('numero_de_serie')->nullable();
    //         $table->text('descripcion')->nullable();
    //         $table->string('ubicacion')->nullable();
    //         $table->decimal('vida_util', 9,2)->nullable();
    //         $table->decimal('valor_compra', 9,2);
    //         $table->decimal('valor_actual', 9,2)->nullable();
    //         $table->decimal('deprecicion', 9,2)->nullable();
    //         $table->integer('responsable_id')->nullable();
    //         $table->integer('usuario_id');
    //         $table->integer('sucursal_id')->nullable();
    //         $table->integer('empresa_id');

    //         $table->timestamps();
    //     });
    // }
    
    // public function down()
    // {
    //     Schema::dropIfExists('empresa_activos');
    // }
}
