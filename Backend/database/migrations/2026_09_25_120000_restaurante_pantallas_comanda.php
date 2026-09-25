<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurante_pantallas')) {
            Schema::create('restaurante_pantallas', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('id_empresa');
                $table->string('nombre', 80);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
                $table->unique(['id_empresa', 'nombre']);
            });
        }

        if (! Schema::hasTable('producto_restaurante_pantalla')) {
            Schema::create('producto_restaurante_pantalla', function (Blueprint $table) {
                $table->unsignedInteger('producto_id');
                $table->unsignedBigInteger('pantalla_id');
                $table->primary(['producto_id', 'pantalla_id']);
                $table->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();
                $table->foreign('pantalla_id')->references('id')->on('restaurante_pantallas')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('restaurante_envio_pantalla')) {
            Schema::create('restaurante_envio_pantalla', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pantalla_id');
                $table->unsignedBigInteger('orden_detalle_id')->nullable();
                $table->unsignedBigInteger('pedido_detalle_id')->nullable();
                $table->timestamps();

                $table->foreign('pantalla_id')->references('id')->on('restaurante_pantallas')->cascadeOnDelete();
                $table->foreign('orden_detalle_id')->references('id')->on('orden_detalle_restaurante')->cascadeOnDelete();
                $table->foreign('pedido_detalle_id')->references('id')->on('restaurante_pedido_detalles')->cascadeOnDelete();
                $table->unique(['pantalla_id', 'orden_detalle_id'], 'uniq_envio_orden_pantalla');
                $table->unique(['pantalla_id', 'pedido_detalle_id'], 'uniq_envio_pedido_pantalla');
            });
        }

        if (Schema::hasTable('comandas_restaurante') && ! Schema::hasColumn('comandas_restaurante', 'pantalla_id')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                $table->unsignedBigInteger('pantalla_id')->nullable()->after('destino');
                $table->foreign('pantalla_id')->references('id')->on('restaurante_pantallas')->nullOnDelete();
            });
        }

        $this->migrarDestinosExistentes();
    }

    public function down(): void
    {
        if (Schema::hasColumn('comandas_restaurante', 'pantalla_id')) {
            Schema::table('comandas_restaurante', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pantalla_id');
            });
        }
        Schema::dropIfExists('restaurante_envio_pantalla');
        Schema::dropIfExists('producto_restaurante_pantalla');
        Schema::dropIfExists('restaurante_pantallas');
    }

    private function migrarDestinosExistentes(): void
    {
        $empresas = DB::table('productos')
            ->where('genera_comanda', 1)
            ->distinct()
            ->pluck('id_empresa')
            ->merge(DB::table('comandas_restaurante')->distinct()->pluck('id_empresa'))
            ->filter()
            ->unique()
            ->values();

        foreach ($empresas as $idEmpresa) {
            $idEmpresa = (int) $idEmpresa;
            $cocinaId = $this->pantallaId($idEmpresa, 'Cocina', 0);
            $barraId = $this->pantallaId($idEmpresa, 'Barra', 1);

            DB::table('productos')
                ->where('id_empresa', $idEmpresa)
                ->where('genera_comanda', 1)
                ->select('id', 'destino_comanda')
                ->orderBy('id')
                ->chunkById(200, function ($productos) use ($cocinaId, $barraId) {
                    $rows = [];
                    foreach ($productos as $producto) {
                        $dest = strtolower(trim((string) ($producto->destino_comanda ?? 'cocina')));
                        $ids = $dest === 'barra' ? [$barraId] : ($dest === 'ambos' ? [$cocinaId, $barraId] : [$cocinaId]);
                        foreach ($ids as $pantallaId) {
                            $rows[] = ['producto_id' => $producto->id, 'pantalla_id' => $pantallaId];
                        }
                    }
                    if ($rows !== []) {
                        DB::table('producto_restaurante_pantalla')->insertOrIgnore($rows);
                    }
                });

            DB::table('comandas_restaurante')
                ->where('id_empresa', $idEmpresa)
                ->where('destino', 'cocina')
                ->whereNull('pantalla_id')
                ->update(['pantalla_id' => $cocinaId]);
            DB::table('comandas_restaurante')
                ->where('id_empresa', $idEmpresa)
                ->where('destino', 'barra')
                ->whereNull('pantalla_id')
                ->update(['pantalla_id' => $barraId]);

            $now = now();
            $ordenCocina = DB::table('orden_detalle_restaurante as od')
                ->join('restaurante_sesiones_mesa as s', 's.id', '=', 'od.sesion_id')
                ->where('s.id_empresa', $idEmpresa)
                ->where('od.enviado_cocina', 1)
                ->whereNull('od.deleted_at')
                ->pluck('od.id');
            $this->insertarEnvios($cocinaId, 'orden_detalle_id', $ordenCocina->all(), $now);

            $ordenBarra = DB::table('orden_detalle_restaurante as od')
                ->join('restaurante_sesiones_mesa as s', 's.id', '=', 'od.sesion_id')
                ->where('s.id_empresa', $idEmpresa)
                ->where('od.enviado_barra', 1)
                ->whereNull('od.deleted_at')
                ->pluck('od.id');
            $this->insertarEnvios($barraId, 'orden_detalle_id', $ordenBarra->all(), $now);

            if (Schema::hasTable('restaurante_pedidos')) {
                $pedidoCocina = DB::table('restaurante_pedido_detalles as d')
                    ->join('restaurante_pedidos as p', 'p.id', '=', 'd.pedido_id')
                    ->where('p.id_empresa', $idEmpresa)
                    ->where('d.enviado_cocina', 1)
                    ->pluck('d.id');
                $this->insertarEnvios($cocinaId, 'pedido_detalle_id', $pedidoCocina->all(), $now);

                $pedidoBarra = DB::table('restaurante_pedido_detalles as d')
                    ->join('restaurante_pedidos as p', 'p.id', '=', 'd.pedido_id')
                    ->where('p.id_empresa', $idEmpresa)
                    ->where('d.enviado_barra', 1)
                    ->pluck('d.id');
                $this->insertarEnvios($barraId, 'pedido_detalle_id', $pedidoBarra->all(), $now);
            }
        }
    }

    private function pantallaId(int $idEmpresa, string $nombre, int $orden): int
    {
        $existente = DB::table('restaurante_pantallas')
            ->where('id_empresa', $idEmpresa)
            ->where('nombre', $nombre)
            ->value('id');
        if ($existente) {
            return (int) $existente;
        }

        return (int) DB::table('restaurante_pantallas')->insertGetId([
            'id_empresa' => $idEmpresa,
            'nombre' => $nombre,
            'orden' => $orden,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $detalleIds
     */
    private function insertarEnvios(int $pantallaId, string $columna, array $detalleIds, $now): void
    {
        foreach (array_chunk($detalleIds, 200) as $chunk) {
            $rows = [];
            foreach ($chunk as $detalleId) {
                $rows[] = [
                    'pantalla_id' => $pantallaId,
                    $columna => (int) $detalleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('restaurante_envio_pantalla')->insertOrIgnore($rows);
        }
    }
};
