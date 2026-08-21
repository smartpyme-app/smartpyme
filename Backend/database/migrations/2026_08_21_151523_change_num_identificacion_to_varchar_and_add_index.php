<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeNumIdentificacionToVarcharAndAddIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('num_identificacion', 80)->nullable()->change();
            $table->index(['id_empresa', 'num_identificacion'], 'idx_ventas_empresa_num_identificacion');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->string('num_identificacion', 80)->nullable()->change();
            $table->index(['id_empresa', 'num_identificacion'], 'idx_compras_empresa_num_identificacion');
        });

        Schema::table('egresos', function (Blueprint $table) {
            $table->string('num_identificacion', 80)->nullable()->change();
            $table->index(['id_empresa', 'num_identificacion'], 'idx_egresos_empresa_num_identificacion');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_empresa_num_identificacion');
            $table->text('num_identificacion')->nullable()->change();
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex('idx_compras_empresa_num_identificacion');
            $table->text('num_identificacion')->nullable()->change();
        });

        Schema::table('egresos', function (Blueprint $table) {
            $table->dropIndex('idx_egresos_empresa_num_identificacion');
            $table->text('num_identificacion')->nullable()->change();
        });
    }
}
