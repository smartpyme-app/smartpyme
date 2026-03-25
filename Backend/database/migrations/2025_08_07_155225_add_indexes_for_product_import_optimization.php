<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesForProductImportOptimization extends Migration
{

    public function up()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->index(['nombre', 'id_empresa'], 'idx_productos_nombre_empresa');
        });

        Schema::table('categorias', function (Blueprint $table) {
            $table->index(['nombre', 'id_empresa'], 'idx_categorias_nombre_empresa');
        });

        Schema::table('sucursal_bodegas', function (Blueprint $table) {
            $table->index(['id_empresa', 'activo'], 'idx_bodegas_empresa_activo');
        });

        Schema::table('inventario', function (Blueprint $table) {
            $table->index(['id_producto', 'id_bodega'], 'idx_inventarios_producto_bodega');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->index(['nombre', 'apellido', 'id_empresa'], 'idx_proveedores_nombre_apellido_empresa');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->index(['nombre_empresa', 'id_empresa'], 'idx_proveedores_empresa_nombre');
        });
    }

    public function down()
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('idx_productos_nombre_empresa');
        });

        Schema::table('categorias', function (Blueprint $table) {
            $table->dropIndex('idx_categorias_nombre_empresa');
        });

        Schema::table('sucursal_bodegas', function (Blueprint $table) {
            $table->dropIndex('idx_bodegas_empresa_activo');
        });

        Schema::table('inventario', function (Blueprint $table) {
            $table->dropIndex('idx_inventarios_producto_bodega');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropIndex('idx_proveedores_nombre_apellido_empresa');
            $table->dropIndex('idx_proveedores_empresa_nombre');
        });
    }
}
