<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdatePrecisionInventarioTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        DB::statement("SET SESSION sql_mode = '';");

        // CORREGIMOS LOS CAMPOS CREATED_AT Y UPDATED_AT DE LAS TABLAS que son del tipo DATETIME y tienen el valor 0000-00-00 00:00:00
        DB::statement("UPDATE inventario SET created_at = '2020-01-01 00:00:00' WHERE created_at = '0000-00-00 00:00:00'");
        DB::statement("UPDATE inventario SET updated_at = '2020-01-01 00:00:00' WHERE updated_at = '0000-00-00 00:00:00'");

        DB::statement("UPDATE productos SET created_at = '2020-01-01 00:00:00' WHERE created_at = '0000-00-00 00:00:00'");
        DB::statement("UPDATE productos SET updated_at = '2020-01-01 00:00:00' WHERE updated_at = '0000-00-00 00:00:00'");

        DB::statement("UPDATE kardexs SET created_at = '2020-01-01 00:00:00' WHERE created_at = '0000-00-00 00:00:00'");
        DB::statement("UPDATE kardexs SET updated_at = '2020-01-01 00:00:00' WHERE updated_at = '0000-00-00 00:00:00'");
        
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio', 16, 6)->change();
            $table->decimal('costo', 16, 6)->change();
            $table->decimal('costo_promedio', 16, 6)->change();
        });

        Schema::table('kardexs', function (Blueprint $table) {
            $table->decimal('entrada_cantidad', 16, 6)->change();
            $table->decimal('salida_cantidad', 16, 6)->change();
            $table->decimal('total_cantidad', 16, 6)->change();
            $table->decimal('costo_unitario', 16, 6)->change();
            $table->decimal('precio_unitario', 16, 6)->change();
        });

        Schema::table('inventario', function (Blueprint $table) {
            $table->decimal('stock', 16, 6)->change();
            $table->decimal('stock_minimo', 16, 6)->change();
            $table->decimal('stock_maximo', 16, 6)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio', 9, 2)->change();
            $table->decimal('costo', 9, 2)->change();
            $table->decimal('costo_promedio', 9, 2)->change();
        });

        Schema::table('kardexs', function (Blueprint $table) {
            $table->decimal('entrada_cantidad', 9, 2)->change();
            $table->decimal('salida_cantidad', 9, 2)->change();
            $table->decimal('total_cantidad', 9, 2)->change();
            $table->decimal('costo_unitario', 9, 2)->change();
            $table->decimal('precio_unitario', 9, 2)->change();
        });

        Schema::table('inventario', function (Blueprint $table) {
            $table->decimal('stock', 10, 2)->change();
            $table->integer('stock_minimo')->change();
            $table->integer('stock_maximo')->change();
        });
    }
}
