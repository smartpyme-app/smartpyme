<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAguinaldosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('aguinaldos')) {
            Schema::create('aguinaldos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_empresa');
                $table->unsignedInteger('id_sucursal');
                $table->year('anio');
                $table->date('fecha_calculo')->nullable();
                $table->decimal('total_aguinaldos', 10, 2)->default(0);
                $table->decimal('total_retenciones', 10, 2)->default(0);
                $table->integer('estado')->default(1); // 1 = Borrador, 2 = Pagado
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
                $table->foreign('id_sucursal')->references('id')->on('sucursales')->onDelete('cascade');
                // Índices
                $table->index(['id_empresa', 'anio']);
                $table->index(['id_sucursal', 'anio']);
                $table->index('estado');
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
        Schema::dropIfExists('aguinaldos');
    }
}
