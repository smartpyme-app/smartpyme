<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurante_zonas')) {
            Schema::create('restaurante_zonas', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_empresa');
                $table->unsignedInteger('id_sucursal')->nullable();
                $table->string('nombre', 80);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
                $table->index(['id_empresa', 'activo']);
            });
        }

        if (! Schema::hasColumn('restaurante_mesas', 'zona_id')) {
            Schema::table('restaurante_mesas', function (Blueprint $table) {
                $table->unsignedBigInteger('zona_id')->nullable()->after('capacidad');
                $table->foreign('zona_id')->references('id')->on('restaurante_zonas')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('restaurante_mesas', 'zona') && Schema::hasTable('restaurante_zonas')) {
            $filas = DB::table('restaurante_mesas')
                ->whereNotNull('zona')
                ->where('zona', '!=', '')
                ->whereNull('zona_id')
                ->select('id_empresa', 'id_sucursal', 'zona')
                ->distinct()
                ->get();

            foreach ($filas as $fila) {
                $zonaId = DB::table('restaurante_zonas')->insertGetId([
                    'id_empresa' => $fila->id_empresa,
                    'id_sucursal' => $fila->id_sucursal,
                    'nombre' => $fila->zona,
                    'orden' => 0,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('restaurante_mesas')
                    ->where('id_empresa', $fila->id_empresa)
                    ->where('zona', $fila->zona)
                    ->whereNull('zona_id')
                    ->update(['zona_id' => $zonaId]);
            }
        }

        if (Schema::hasTable('restaurante_pedido_detalles')) {
            if (! Schema::hasColumn('restaurante_pedido_detalles', 'enviado_cocina')) {
                Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
                    $table->boolean('enviado_cocina')->default(false)->after('notas');
                    $table->boolean('enviado_barra')->default(false)->after('enviado_cocina');
                });
            }
        }

        if (Schema::hasTable('comandas_restaurante')) {
            if (! Schema::hasColumn('comandas_restaurante', 'pedido_id')) {
                Schema::table('comandas_restaurante', function (Blueprint $table) {
                    $table->unsignedBigInteger('pedido_id')->nullable()->after('sesion_id');
                    $table->foreign('pedido_id')->references('id')->on('restaurante_pedidos')->cascadeOnDelete();
                });
            }

            Schema::table('comandas_restaurante', function (Blueprint $table) {
                if (Schema::hasColumn('comandas_restaurante', 'sesion_id')) {
                    $table->unsignedBigInteger('sesion_id')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('comanda_detalle_restaurante')) {
            if (! Schema::hasColumn('comanda_detalle_restaurante', 'pedido_detalle_id')) {
                Schema::table('comanda_detalle_restaurante', function (Blueprint $table) {
                    $table->unsignedBigInteger('pedido_detalle_id')->nullable()->after('orden_detalle_id');
                    $table->foreign('pedido_detalle_id')->references('id')->on('restaurante_pedido_detalles')->cascadeOnDelete();
                });
            }

            Schema::table('comanda_detalle_restaurante', function (Blueprint $table) {
                if (Schema::hasColumn('comanda_detalle_restaurante', 'orden_detalle_id')) {
                    $table->unsignedBigInteger('orden_detalle_id')->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('comanda_detalle_restaurante', 'pedido_detalle_id')) {
            Schema::table('comanda_detalle_restaurante', function (Blueprint $table) {
                $table->dropForeign(['pedido_detalle_id']);
                $table->dropColumn('pedido_detalle_id');
            });
        }

        if (Schema::hasColumn('comandas_restaurante', 'pedido_id')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                $table->dropForeign(['pedido_id']);
                $table->dropColumn('pedido_id');
            });
        }

        if (Schema::hasColumn('restaurante_pedido_detalles', 'enviado_cocina')) {
            Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
                $table->dropColumn(['enviado_cocina', 'enviado_barra']);
            });
        }

        if (Schema::hasColumn('restaurante_mesas', 'zona_id')) {
            Schema::table('restaurante_mesas', function (Blueprint $table) {
                $table->dropForeign(['zona_id']);
                $table->dropColumn('zona_id');
            });
        }

        Schema::dropIfExists('restaurante_zonas');
    }
};
