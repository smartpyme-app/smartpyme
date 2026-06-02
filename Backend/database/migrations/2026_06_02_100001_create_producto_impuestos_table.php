<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateProductoImpuestosTable extends Migration
{
    public function up()
    {
        Schema::create('producto_impuestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_producto');
            $table->integer('id_impuesto');
            $table->timestamps();

            $table->foreign('id_producto')
                ->references('id')
                ->on('productos')
                ->onDelete('cascade');

            $table->foreign('id_impuesto')
                ->references('id')
                ->on('impuestos')
                ->onDelete('cascade');

            $table->unique(['id_producto', 'id_impuesto']);
        });

        // Productos existentes: vincular impuesto del catálogo que coincida con porcentaje_impuesto.
        if (Schema::hasTable('productos') && Schema::hasTable('impuestos')) {
            $productos = DB::table('productos')
                ->whereNotNull('porcentaje_impuesto')
                ->where('porcentaje_impuesto', '>', 0)
                ->select('id', 'id_empresa', 'porcentaje_impuesto')
                ->get();

            foreach ($productos as $producto) {
                $idImpuesto = DB::table('impuestos')
                    ->where('id_empresa', $producto->id_empresa)
                    ->where('aplica_ventas', 1)
                    ->where('porcentaje', $producto->porcentaje_impuesto)
                    ->orderBy('id')
                    ->value('id');

                if (!$idImpuesto) {
                    continue;
                }

                $exists = DB::table('producto_impuestos')
                    ->where('id_producto', $producto->id)
                    ->where('id_impuesto', $idImpuesto)
                    ->exists();

                if (!$exists) {
                    DB::table('producto_impuestos')->insert([
                        'id_producto' => $producto->id,
                        'id_impuesto' => $idImpuesto,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('producto_impuestos');
    }
}
