<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('productos', 'destino_comanda')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->string('destino_comanda', 20)->default('cocina')->after('genera_comanda')
                    ->comment('cocina, barra, ambos');
            });
        }

        if (!Schema::hasColumn('orden_detalle_restaurante', 'enviado_barra')) {
            Schema::table('orden_detalle_restaurante', function (Blueprint $table) {
                $table->boolean('enviado_barra')->default(false)->after('enviado_cocina');
            });
        }
        if (!Schema::hasColumn('orden_detalle_restaurante', 'deleted_at')) {
            Schema::table('orden_detalle_restaurante', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('comandas_restaurante', 'destino')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                $table->string('destino', 20)->default('cocina')->after('estado')
                    ->comment('cocina, barra, eliminacion');
            });
        }

        if (!Schema::hasTable('rest_item_eliminaciones_log')) {
            Schema::create('rest_item_eliminaciones_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orden_detalle_id')->nullable()->comment('ID antes de anular');
                $table->foreignId('sesion_id')->constrained('restaurante_sesiones_mesa')->cascadeOnDelete();
                $table->unsignedInteger('producto_id');
                $table->decimal('cantidad', 10, 2);
                $table->decimal('precio_unitario', 12, 2)->default(0);
                $table->text('notas')->nullable();
                $table->boolean('enviado_cocina')->default(false);
                $table->boolean('enviado_barra')->default(false);
                $table->string('motivo_codigo', 50)->nullable()->comment('error, rechazo_cliente, otro');
                $table->text('motivo_detalle')->nullable();
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedBigInteger('autorizado_usuario_id')->nullable();
                $table->timestamps();

                $table->foreign('producto_id', 'fk_rest_elim_prod')->references('id')->on('productos')->restrictOnDelete();
                $table->foreign('usuario_id', 'fk_rest_elim_user')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('autorizado_usuario_id', 'fk_rest_elim_autor')->references('id')->on('users')->nullOnDelete();
                $table->index(['sesion_id', 'created_at'], 'idx_rest_elim_sesion_created');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rest_item_eliminaciones_log');

        if (Schema::hasColumn('comandas_restaurante', 'destino')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                $table->dropColumn('destino');
            });
        }

        if (Schema::hasColumn('orden_detalle_restaurante', 'deleted_at')) {
            Schema::table('orden_detalle_restaurante', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
        if (Schema::hasColumn('orden_detalle_restaurante', 'enviado_barra')) {
            Schema::table('orden_detalle_restaurante', function (Blueprint $table) {
                $table->dropColumn('enviado_barra');
            });
        }

        if (Schema::hasColumn('productos', 'destino_comanda')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('destino_comanda');
            });
        }
    }
};
