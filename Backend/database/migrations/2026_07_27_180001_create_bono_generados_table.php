<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBonoGeneradosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bono_generados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_vendedor');
            $table->unsignedBigInteger('id_regla');
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->decimal('monto_ventas_base', 14, 4)->default(0);
            $table->decimal('monto', 14, 4);
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->timestamp('pagado_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['id_empresa', 'id_vendedor', 'id_regla', 'periodo_inicio', 'periodo_fin'],
                'bono_generados_unique_eval'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bono_generados');
    }
}
