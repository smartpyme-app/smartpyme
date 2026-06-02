<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdPresentacionToDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. tabla de detalles VENTAS
        Schema::table('detalles_venta', function (Blueprint $table) {
            $table->unsignedBigInteger('id_presentacion')->nullable()->after('id_producto');
            $table->foreign('id_presentacion')->references('id')->on('producto_presentaciones')->onDelete('set null');
        });

        // 2. tabla de detalles COMPRAS
        Schema::table('detalles_compra', function (Blueprint $table) {
            $table->unsignedBigInteger('id_presentacion')->nullable()->after('id_producto');
            $table->foreign('id_presentacion')->references('id')->on('producto_presentaciones')->onDelete('set null');
        });

        // 3. tabla de TRASLADOS
        Schema::table('traslados', function (Blueprint $table) {
            $table->unsignedBigInteger('id_presentacion')->nullable()->after('id_producto');
            $table->foreign('id_presentacion')->references('id')->on('producto_presentaciones')->onDelete('set null');
        });

        // 4. tabla de AJUSTES
        Schema::table('ajustes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_presentacion')->nullable()->after('id_producto');
            $table->foreign('id_presentacion')->references('id')->on('producto_presentaciones')->onDelete('set null');
        });

        // 5. tabla de ENTRADAS DETALLES
        Schema::table('inventario_entrada_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('id_presentacion')->nullable()->after('id_producto');
            $table->foreign('id_presentacion')->references('id')->on('producto_presentaciones')->onDelete('set null');
        });

        // 6. tabla de SALIDAS DETALLES
        Schema::table('inventario_salida_detalles', function (Blueprint $table) {
            $table->unsignedBigInteger('id_presentacion')->nullable()->after('id_producto');
            $table->foreign('id_presentacion')->references('id')->on('producto_presentaciones')->onDelete('set null');
        });

        // 7. tabla de PRODUCTO COMPOSICIONES
        Schema::table('producto_composiciones', function (Blueprint $table) {
            $table->unsignedBigInteger('id_presentacion')->nullable()->after('cantidad');
            $table->foreign('id_presentacion')->references('id')->on('producto_presentaciones')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revertimos COMPRAS
        Schema::table('detalles_compra', function (Blueprint $table) {
            $table->dropForeign(['id_presentacion']);
            $table->dropColumn('id_presentacion');
        });

        // Revertimos VENTAS
        Schema::table('detalles_venta', function (Blueprint $table) {
            $table->dropForeign(['id_presentacion']);
            $table->dropColumn('id_presentacion');
        });

        // Revertimos TRASLADOS
        Schema::table('traslados', function (Blueprint $table) {
            $table->dropForeign(['id_presentacion']);
            $table->dropColumn('id_presentacion');
        });

        // Revertimos AJUSTES
        Schema::table('ajustes', function (Blueprint $table) {
            $table->dropForeign(['id_presentacion']);
            $table->dropColumn('id_presentacion');
        });

        // Revertimos ENTRADAS DETALLES
        Schema::table('inventario_entrada_detalles', function (Blueprint $table) {
            $table->dropForeign(['id_presentacion']);
            $table->dropColumn('id_presentacion');
        });

        // Revertimos SALIDAS DETALLES
        Schema::table('inventario_salida_detalles', function (Blueprint $table) {
            $table->dropForeign(['id_presentacion']);
            $table->dropColumn('id_presentacion');
        });

        // Revertimos PRODUCTO COMPOSICIONES
        Schema::table('producto_composiciones', function (Blueprint $table) {
            $table->dropForeign(['id_presentacion']);
            $table->dropColumn('id_presentacion');
        });
    }
}