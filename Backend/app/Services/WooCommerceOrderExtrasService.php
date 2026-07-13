<?php

namespace App\Services;

use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Detalle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class WooCommerceOrderExtrasService
{
    protected $impuestosService;

    public function __construct(ImpuestosService $impuestosService)
    {
        $this->impuestosService = $impuestosService;
    }

    /**
     * @return Detalle[]
     */
    public function procesarEnvios(array $shippingLines, int $ventaId, int $empresaId, int $usuarioId, int $sucursalId): array
    {
        $detalles = [];

        foreach ($shippingLines as $shippingLine) {
            $titulo = trim((string) ($shippingLine['method_title'] ?? ''));
            $total = (float) ($shippingLine['total'] ?? 0);
            $totalTax = (float) ($shippingLine['total_tax'] ?? 0);

            if ($titulo === '' || $total <= 0) {
                continue;
            }

            $detalle = $this->crearDetalleServicio(
                $titulo,
                $total,
                $totalTax,
                'envios',
                $ventaId,
                $empresaId,
                $usuarioId,
                $sucursalId
            );

            if ($detalle) {
                $detalles[] = $detalle;
            }
        }

        return $detalles;
    }

    /**
     * @return Detalle[]
     */
    public function procesarRecargos(array $feeLines, int $ventaId, int $empresaId, int $usuarioId, int $sucursalId): array
    {
        $detalles = [];

        foreach ($feeLines as $feeLine) {
            $nombre = trim((string) ($feeLine['name'] ?? ''));
            $total = (float) ($feeLine['total'] ?? 0);
            $totalTax = (float) ($feeLine['total_tax'] ?? 0);

            if ($nombre === '' || $total <= 0) {
                continue;
            }

            $detalle = $this->crearDetalleServicio(
                $nombre,
                $total,
                $totalTax,
                'recargos',
                $ventaId,
                $empresaId,
                $usuarioId,
                $sucursalId
            );

            if ($detalle) {
                $detalles[] = $detalle;
            }
        }

        return $detalles;
    }

    private function crearDetalleServicio(
        string $nombre,
        float $total,
        float $totalTax,
        string $categoriaNombre,
        int $ventaId,
        int $empresaId,
        int $usuarioId,
        int $sucursalId
    ): ?Detalle {
        $producto = $this->buscarOCrearProductoServicio(
            $nombre,
            $total,
            $categoriaNombre,
            $empresaId,
            $usuarioId,
            $sucursalId
        );

        if (!$producto) {
            Log::error('No se pudo crear producto de servicio WooCommerce', [
                'nombre' => $nombre,
                'categoria' => $categoriaNombre,
                'empresa_id' => $empresaId,
            ]);

            return null;
        }

        $gravada = $totalTax > 0 ? max(0, $total - $totalTax) : $total;

        return Detalle::create([
            'id_venta' => $ventaId,
            'id_producto' => $producto->id,
            'descripcion' => $nombre,
            'cantidad' => 1,
            'precio' => $total,
            'costo' => 0,
            'descuento' => 0,
            'subtotal' => $total,
            'gravada' => $gravada,
            'exenta' => 0,
            'no_sujeta' => 0,
            'cuenta_a_terceros' => 0,
            'total_costo' => 0,
            'total' => $total,
            'iva' => $totalTax,
            'id_vendedor' => $usuarioId,
        ]);
    }

    private function buscarOCrearProductoServicio(
        string $nombre,
        float $precio,
        string $categoriaNombre,
        int $empresaId,
        int $usuarioId,
        int $sucursalId
    ): ?Producto {
        $producto = Producto::where('nombre', $nombre)
            ->where('id_empresa', $empresaId)
            ->where('tipo', 'Servicio')
            ->whereHas('categoria', function ($query) use ($categoriaNombre) {
                $query->where('nombre', $categoriaNombre);
            })
            ->first();

        if ($producto) {
            if (abs($producto->precio - $precio) > 0.01) {
                $producto->update(['precio' => $precio]);
            }

            return $producto;
        }

        return Model::withoutEvents(function () use (
            $nombre,
            $precio,
            $categoriaNombre,
            $empresaId,
            $usuarioId,
            $sucursalId
        ) {
            return Producto::create([
                'nombre' => $nombre,
                'descripcion' => 'Servicio WooCommerce: ' . $nombre,
                'codigo' => strtoupper(substr($categoriaNombre, 0, 3)) . '-' . strtoupper(substr(md5($nombre), 0, 8)),
                'tipo' => 'Servicio',
                'precio' => $precio,
                'costo' => 0,
                'id_categoria' => Categoria::firstOrCreate(
                    [
                        'nombre' => $categoriaNombre,
                        'id_empresa' => $empresaId,
                    ],
                    [
                        'descripcion' => 'Servicios sincronizados desde WooCommerce',
                        'enable' => 1,
                    ]
                )->id,
                'id_empresa' => $empresaId,
                'id_usuario' => $usuarioId,
                'id_sucursal' => $sucursalId,
                'enable' => 1,
                'control_stock' => 0,
            ]);
        });
    }
}
