<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_activos_categorias', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre');
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('plantilla_id')->nullable();
            $table->string('metodo_depreciacion', 32)->default('linea_recta');
            $table->decimal('porcentaje_anual', 5, 2)->nullable();
            $table->decimal('vida_util_anios', 5, 2)->nullable();
            $table->decimal('valor_residual_default', 9, 2)->default(0);
            $table->boolean('permite_bien_usado')->default(false);
            $table->json('reglas_bien_usado')->nullable();
            $table->json('metadata_schema')->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'nombre']);
        });

        Schema::create('empresa_activos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre');
            $table->string('referencia')->nullable();
            $table->date('fecha_compra');
            $table->date('fecha_retiro')->nullable();
            $table->string('estado', 32)->default('En uso');
            $table->unsignedInteger('id_categoria');
            $table->string('numero_de_serie')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('ubicacion')->nullable();
            $table->decimal('vida_util', 9, 2)->nullable();
            $table->decimal('valor_compra', 9, 2);
            $table->decimal('depreciacion_acumulada', 9, 2)->default(0);
            $table->decimal('valor_en_libros', 9, 2)->nullable();
            $table->decimal('valor_residual', 9, 2)->default(0);
            $table->boolean('es_usado')->default(false);
            $table->decimal('porcentaje_base_usado', 5, 2)->nullable();
            $table->date('fecha_inicio_depreciacion')->nullable();
            $table->string('estado_registro', 16)->default('activo');
            $table->json('metadata')->nullable();
            $table->unsignedInteger('id_egreso')->nullable();
            $table->unsignedInteger('id_compra_detalle')->nullable();
            $table->unsignedInteger('id_responsable')->nullable();
            $table->unsignedInteger('id_usuario');
            $table->unsignedInteger('id_sucursal')->nullable();
            $table->unsignedInteger('id_empresa');
            $table->timestamps();

            $table->index(['id_empresa', 'estado_registro']);
            $table->index(['id_empresa', 'id_categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_activos');
        Schema::dropIfExists('empresa_activos_categorias');
    }
};
