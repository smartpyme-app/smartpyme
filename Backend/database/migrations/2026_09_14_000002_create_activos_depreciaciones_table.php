<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_activos_depreciaciones', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_activo');
            $table->unsignedInteger('id_empresa');
            $table->char('periodo', 7);
            $table->decimal('monto', 9, 2);
            $table->decimal('depreciacion_acumulada', 9, 2);
            $table->decimal('valor_en_libros', 9, 2);
            $table->string('estado', 16)->default('pendiente');
            $table->unsignedInteger('id_egreso')->nullable();
            $table->timestamps();

            $table->unique(['id_activo', 'periodo']);
            $table->index(['id_empresa', 'periodo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_activos_depreciaciones');
    }
};
