<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Clientes\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FusionarClientesDuplicadosService
{
    public const TABLAS_REASIGNAR = [
        'ventas',
        'devoluciones_venta',
        'creditos',
        'credito_contratos',
        'contactos_cliente',
        'cotizaciones',
        'cotizacion_ventas',
        'eventos',
        'paquetes',
        'ordenes_produccion',
        'proyectos',
        'transacciones_puntos',
        'consumo_puntos',
        'cliente_notas',
        'cliente_visitas',
    ];

    public const TABLAS_SNAPSHOT = [
        'cliente_ventas_mensuales',
        'cliente_fidelizacion_snapshot',
        'cliente_metricas_rfm',
        'cliente_productos_top',
        'cliente_categorias_preferidas',
        'cliente_actividad_reciente',
    ];

    public static function normalizarDocumento(?string $doc): string
    {
        if ($doc === null) {
            return '';
        }

        return strtoupper((string) preg_replace('/[\s\-]/', '', $doc));
    }

    public static function claveGrupo(?string $nit, ?string $dui): ?string
    {
        $nitNorm = self::normalizarDocumento($nit);
        if ($nitNorm !== '') {
            return 'nit:'.$nitNorm;
        }

        $duiNorm = self::normalizarDocumento($dui);
        if ($duiNorm !== '') {
            return 'dui:'.$duiNorm;
        }

        return null;
    }

    /**
     * @param  array<int, array{id:int,enable:mixed}>  $clientes
     * @return array{accion:string,destino:?int,origenes:array<int>,razon:string}
     */
    public static function clasificarGrupo(array $clientes): array
    {
        $habilitados = [];
        $inhabilitados = [];

        foreach ($clientes as $cliente) {
            $id = (int) $cliente['id'];
            if (self::estaHabilitado($cliente['enable'] ?? false)) {
                $habilitados[] = $id;
            } else {
                $inhabilitados[] = $id;
            }
        }

        if (count($habilitados) === 1 && count($inhabilitados) >= 1) {
            return [
                'accion' => 'fusionar',
                'destino' => $habilitados[0],
                'origenes' => $inhabilitados,
                'razon' => '',
            ];
        }

        $razon = 'sin habilitado único';
        if (count($habilitados) > 1) {
            $razon = 'más de un habilitado';
        } elseif (count($habilitados) === 0) {
            $razon = 'ningún habilitado';
        } elseif (count($inhabilitados) === 0) {
            $razon = 'sin inhabilitados';
        }

        return [
            'accion' => 'saltar',
            'destino' => $habilitados[0] ?? null,
            'origenes' => $inhabilitados,
            'razon' => $razon,
        ];
    }

    /**
     * @return array{sin_documento: array<int, array<string, mixed>>, grupos: array<int, array<string, mixed>>}
     */
    public function planear(int $empresaId): array
    {
        $clientes = Cliente::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresaId)
            ->get(['id', 'enable', 'nit', 'dui', 'nombre', 'nombre_empresa', 'tipo']);

        $sinDocumento = [];
        $porClave = [];

        foreach ($clientes as $cliente) {
            $clave = self::claveGrupo($cliente->nit, $cliente->dui);
            $fila = [
                'id' => (int) $cliente->id,
                'enable' => $cliente->enable,
                'nit' => $cliente->nit,
                'dui' => $cliente->dui,
                'nombre' => $cliente->tipo === 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre_completo,
            ];

            if ($clave === null) {
                $sinDocumento[] = $fila;
                continue;
            }

            $porClave[$clave][] = $fila;
        }

        $grupos = [];
        foreach ($porClave as $clave => $miembros) {
            $clasificacion = self::clasificarGrupo($miembros);
            $ventasOrigen = 0;
            foreach ($clasificacion['origenes'] as $origenId) {
                $ventasOrigen += (int) DB::table('ventas')->where('id_cliente', $origenId)->count();
            }

            $grupos[] = [
                'clave' => $clave,
                'accion' => $clasificacion['accion'],
                'destino' => $clasificacion['destino'],
                'origenes' => $clasificacion['origenes'],
                'razon' => $clasificacion['razon'],
                'ventas_origen' => $ventasOrigen,
                'miembros' => $miembros,
            ];
        }

        return [
            'sin_documento' => $sinDocumento,
            'grupos' => $grupos,
        ];
    }

    /**
     * @param  array{sin_documento: array, grupos: array<int, array<string, mixed>>}  $plan
     * @return array{fusionados:int,saltados:int,errores:array<int, array{origen:int,mensaje:string}>}
     */
    public function ejecutarPlan(array $plan, bool $ejecutar): array
    {
        $fusionados = 0;
        $saltados = count($plan['sin_documento']);
        $errores = [];

        foreach ($plan['grupos'] as $grupo) {
            if ($grupo['accion'] !== 'fusionar') {
                $saltados++;
                continue;
            }

            if (! $ejecutar) {
                $fusionados += count($grupo['origenes']);
                continue;
            }

            foreach ($grupo['origenes'] as $origenId) {
                try {
                    $this->fusionarPar((int) $grupo['destino'], (int) $origenId);
                    $fusionados++;
                } catch (Throwable $e) {
                    $errores[] = [
                        'origen' => (int) $origenId,
                        'mensaje' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'fusionados' => $fusionados,
            'saltados' => $saltados,
            'errores' => $errores,
        ];
    }

    private function fusionarPar(int $destinoId, int $origenId): void
    {
        DB::transaction(function () use ($destinoId, $origenId) {
            foreach (self::TABLAS_REASIGNAR as $tabla) {
                if (! Schema::hasTable($tabla)) {
                    continue;
                }
                DB::table($tabla)->where('id_cliente', $origenId)->update(['id_cliente' => $destinoId]);
            }

            $this->fusionarPuntosCliente($destinoId, $origenId);

            foreach (self::TABLAS_SNAPSHOT as $tabla) {
                if (! Schema::hasTable($tabla)) {
                    continue;
                }
                DB::table($tabla)->where('id_cliente', $origenId)->delete();
            }

            $restantes = $this->filasRestantes($origenId);
            if ($restantes !== []) {
                throw new \RuntimeException('Quedan FKs en: '.implode(', ', $restantes));
            }

            $borradas = DB::table('clientes')->where('id', $origenId)->delete();
            if ($borradas === 0) {
                throw new \RuntimeException('No se pudo borrar el cliente '.$origenId);
            }
        });
    }

    private function fusionarPuntosCliente(int $destinoId, int $origenId): void
    {
        if (! Schema::hasTable('puntos_cliente')) {
            return;
        }

        $origen = DB::table('puntos_cliente')->where('id_cliente', $origenId)->first();
        if (! $origen) {
            return;
        }

        $destino = DB::table('puntos_cliente')->where('id_cliente', $destinoId)->first();
        if (! $destino) {
            DB::table('puntos_cliente')->where('id', $origen->id)->update(['id_cliente' => $destinoId]);

            return;
        }

        DB::table('puntos_cliente')->where('id', $destino->id)->update([
            'puntos_disponibles' => (int) $destino->puntos_disponibles + (int) $origen->puntos_disponibles,
            'puntos_totales_ganados' => (int) $destino->puntos_totales_ganados + (int) $origen->puntos_totales_ganados,
            'puntos_totales_canjeados' => (int) $destino->puntos_totales_canjeados + (int) $origen->puntos_totales_canjeados,
        ]);
        DB::table('puntos_cliente')->where('id', $origen->id)->delete();
    }

    /**
     * @return array<int, string>
     */
    private function filasRestantes(int $origenId): array
    {
        $restantes = [];
        foreach (self::TABLAS_REASIGNAR as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            if (DB::table($tabla)->where('id_cliente', $origenId)->exists()) {
                $restantes[] = $tabla;
            }
        }

        if (Schema::hasTable('puntos_cliente') && DB::table('puntos_cliente')->where('id_cliente', $origenId)->exists()) {
            $restantes[] = 'puntos_cliente';
        }

        return $restantes;
    }

    private static function estaHabilitado(mixed $enable): bool
    {
        return $enable === true || $enable === 1 || $enable === '1';
    }
}
