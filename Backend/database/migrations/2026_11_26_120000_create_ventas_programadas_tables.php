<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_programadas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->integer('id_cliente');
            $table->unsignedInteger('id_documento');
            $table->integer('id_sucursal')->nullable();
            $table->integer('id_bodega')->nullable();
            $table->unsignedBigInteger('id_vendedor')->nullable();
            $table->string('forma_pago', 255)->nullable();
            $table->string('condicion', 255)->nullable();
            $table->double('sub_total')->default(0);
            $table->double('descuento')->default(0);
            $table->decimal('iva', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('gravada', 10, 2)->nullable();
            $table->decimal('exenta', 9, 2)->nullable();
            $table->decimal('no_sujeta', 9, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('frecuencia', 10);
            $table->unsignedTinyInteger('dia_ejecucion');
            $table->time('hora_ejecucion');
            $table->dateTime('proxima_ejecucion');
            $table->boolean('activo')->default(true);
            $table->boolean('emitir_automatico')->default(false);
            $table->boolean('enviar_email')->default(false);
            $table->unsignedInteger('max_ejecuciones')->nullable();
            $table->unsignedInteger('ejecuciones_count')->default(0);
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->timestamps();

            $table->index('proxima_ejecucion');
            $table->index(['id_empresa', 'activo', 'proxima_ejecucion'], 'ventas_prog_empresa_activo_prox_idx');

            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_cliente')->references('id')->on('clientes');
            $table->foreign('id_documento')->references('id')->on('documentos');
            $table->foreign('id_sucursal')->references('id')->on('sucursales')->nullOnDelete();
            $table->foreign('id_bodega')->references('id')->on('sucursal_bodegas')->nullOnDelete();
            $table->foreign('id_vendedor')->references('id')->on('users')->nullOnDelete();
            $table->foreign('id_usuario')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('detalles_venta_programada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_venta_programada')->constrained('ventas_programadas')->cascadeOnDelete();
            $table->unsignedInteger('id_producto');
            $table->unsignedBigInteger('id_presentacion')->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio', 16, 8);
            $table->decimal('precio_sin_iva', 16, 8)->nullable();
            $table->decimal('precio_con_iva', 16, 6)->nullable();
            $table->decimal('costo', 16, 6)->default(0);
            $table->decimal('descuento', 9, 2)->default(0);
            $table->decimal('sub_total', 12, 4)->nullable();
            $table->string('tipo_gravado', 20)->default('gravada');
            $table->decimal('no_sujeta', 9, 2)->default(0);
            $table->decimal('exenta', 9, 2)->default(0);
            $table->decimal('gravada', 10, 2)->default(0);
            $table->decimal('cuenta_a_terceros', 9, 2)->default(0);
            $table->decimal('total_costo', 9, 2)->default(0);
            $table->decimal('total', 9, 2);
            $table->unsignedBigInteger('id_vendedor')->nullable();
            $table->decimal('iva', 9, 4)->default(0);
            $table->decimal('porcentaje_impuesto', 5, 2)->nullable();
            $table->json('fe_cr_exoneracion')->nullable();
            $table->timestamps();

            $table->foreign('id_producto')->references('id')->on('productos');
            $table->foreign('id_presentacion')->references('id')->on('producto_presentaciones')->nullOnDelete();
            $table->foreign('id_vendedor')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('ventas_programadas_ejecuciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_venta_programada')->constrained('ventas_programadas')->cascadeOnDelete();
            $table->unsignedInteger('id_venta')->nullable();
            $table->string('periodo', 20);
            $table->dateTime('programada_para');
            $table->dateTime('ejecutada_en')->nullable();
            $table->string('estado', 32);
            $table->string('modo', 32)->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamps();

            $table->index(['id_venta_programada', 'periodo'], 'ventas_prog_ejec_periodo_idx');

            $table->foreign('id_venta')->references('id')->on('ventas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_programadas_ejecuciones');
        Schema::dropIfExists('detalles_venta_programada');
        Schema::dropIfExists('ventas_programadas');
    }
};