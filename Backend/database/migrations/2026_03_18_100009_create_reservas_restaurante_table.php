<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReservasRestauranteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reservas_restaurante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesa_id')->constrained('restaurante_mesas')->onDelete('cascade');
            $table->unsignedInteger('id_empresa');
            $table->date('fecha_reserva');
            $table->time('hora_reserva');
            $table->string('cliente_nombre', 150)->nullable();
            $table->string('cliente_telefono', 30)->nullable();
            $table->text('observaciones')->nullable();
            $table->enum('estado', ['pendiente', 'confirmada', 'cumplida', 'cancelada', 'no_show'])->default('pendiente');
            $table->unsignedBigInteger('usuario_id')->nullable()->comment('Usuario que creó la reserva');
            $table->unsignedBigInteger('cliente_id')->nullable()->comment('Cliente del sistema si existe');
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['mesa_id', 'fecha_reserva', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservas_restaurante');
    }
}
