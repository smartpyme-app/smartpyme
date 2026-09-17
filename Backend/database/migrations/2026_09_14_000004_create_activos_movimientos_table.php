<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_activos_movimientos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_activo');
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_usuario');
            $table->string('tipo', 32);
            $table->date('fecha');
            $table->text('descripcion')->nullable();
            $table->decimal('monto', 9, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['id_activo', 'fecha']);
            $table->index(['id_empresa', 'tipo', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_activos_movimientos');
    }
};
