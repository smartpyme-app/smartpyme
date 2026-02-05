<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('lotes')) {
            Schema::create('lotes', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('id_producto');
                $table->unsignedInteger('id_bodega');
                $table->string('numero_lote')->nullable();
                $table->date('fecha_vencimiento')->nullable();
                $table->date('fecha_fabricacion')->nullable();
                $table->decimal('stock', 9, 2)->default(0);
                $table->decimal('stock_inicial', 9, 2)->default(0);
                $table->unsignedInteger('id_empresa');
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->softDeletes();
                
                $table->index(['id_producto', 'id_bodega']);
                $table->index('fecha_vencimiento');
            });
            
            // Agregar foreign keys después de crear la tabla
            Schema::table('lotes', function (Blueprint $table) {
                $table->foreign('id_producto')->references('id')->on('productos')->onDelete('cascade');
                $table->foreign('id_bodega')->references('id')->on('sucursal_bodegas')->onDelete('cascade');
                $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lotes');
    }
}
