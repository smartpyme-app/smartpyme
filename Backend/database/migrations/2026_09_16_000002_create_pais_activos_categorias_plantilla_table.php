<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pais_activos_categorias_plantilla', function (Blueprint $table) {
            $table->increments('id');
            $table->char('cod_pais', 3);
            $table->string('nombre');
            $table->string('metodo_depreciacion', 32)->default('linea_recta');
            $table->decimal('porcentaje_anual', 5, 2)->nullable();
            $table->decimal('vida_util_anios', 5, 2)->nullable();
            $table->decimal('valor_residual_default', 9, 2)->default(0);
            $table->boolean('permite_bien_usado')->default(false);
            $table->json('reglas_bien_usado')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['cod_pais', 'activo']);
            $table->unique(['cod_pais', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pais_activos_categorias_plantilla');
    }
};
