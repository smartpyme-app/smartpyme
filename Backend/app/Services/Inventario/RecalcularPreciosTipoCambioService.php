<?php

namespace App\Services\Inventario;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use App\Services\Moneda\MonedaPaisService;
use App\Support\Inventario\RecalcularPreciosTipoCambio;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RecalcularPreciosTipoCambioService
{
    public function __construct(private readonly MonedaPaisService $moneda) {}

    public function assertPermitido(Empresa $empresa): void
    {
        $pais = FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);
        if ($pais !== FacturacionElectronicaCountryResolver::CODIGO_HONDURAS
            || ! $empresa->tieneFuncionalidadMultimoneda()
        ) {
            throw new HttpException(403, 'Solo disponible para Honduras con multimoneda.');
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(Empresa $empresa): array
    {
        $this->assertPermitido($empresa);
        $preview = $this->moneda->preview($empresa);
        $venta = $this->floatOrNull($empresa->getCustomConfigValue('configuraciones', RecalcularPreciosTipoCambio::KEY_VENTA));
        $catalogo = $this->floatOrNull($empresa->getCustomConfigValue('configuraciones', RecalcularPreciosTipoCambio::KEY_CATALOGO));
        $api = $this->floatOrNull($preview['rate_api'] ?? null);

        return [
            'rate' => RecalcularPreciosTipoCambio::sugeridoVenta($venta, $api),
            'rate_api' => $api,
            'rate_venta' => $venta,
            'rate_catalogo' => $catalogo,
            'tiene_catalogo' => $catalogo !== null && $catalogo > 0,
            'permitir_editar' => (bool) ($preview['permitir_editar'] ?? true),
            'date' => $preview['date'] ?? null,
            'error' => $preview['error'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public function guardarVenta(Empresa $empresa, float $rate): array
    {
        $this->assertPermitido($empresa);
        $catalogo = $this->floatOrNull($empresa->getCustomConfigValue('configuraciones', RecalcularPreciosTipoCambio::KEY_CATALOGO));
        $r = RecalcularPreciosTipoCambio::sembrarCatalogoSiFalta($catalogo, $rate);
        $empresa->updateCustomConfig('configuraciones', RecalcularPreciosTipoCambio::KEY_VENTA, $r['venta']);
        if ($r['sembrar']) {
            $empresa->updateCustomConfig('configuraciones', RecalcularPreciosTipoCambio::KEY_CATALOGO, $r['catalogo']);
        }
        unset($empresa->custom_config);

        return $this->snapshot($empresa);
    }

    /**
     * @return array<string, mixed>
     */
    public function recalcular(Empresa $empresa, float $rate, bool $aplicarProductos, bool $aplicarServicios): array
    {
        $this->assertPermitido($empresa);
        $tipos = RecalcularPreciosTipoCambio::tipos($aplicarProductos, $aplicarServicios);
        $catalogo = $this->floatOrNull($empresa->getCustomConfigValue('configuraciones', RecalcularPreciosTipoCambio::KEY_CATALOGO));
        if ($catalogo === null || $catalogo <= 0) {
            throw new InvalidArgumentException('Primero guarde el tipo de cambio inicial.');
        }
        $factor = RecalcularPreciosTipoCambio::factor($rate, $catalogo);

        $actualizados = 0;
        DB::transaction(function () use ($empresa, $tipos, $factor, $rate, &$actualizados) {
            Producto::query()
                ->whereIn('tipo', $tipos)
                ->with('precios')
                ->chunkById(200, function ($productos) use ($factor, &$actualizados) {
                    foreach ($productos as $producto) {
                        $data = RecalcularPreciosTipoCambio::aplicarAProducto([
                            'precio' => $producto->precio,
                            'precio_sin_iva' => $producto->precio_sin_iva,
                            'precio_con_iva' => $producto->precio_con_iva,
                            'precios' => $producto->precios->map(static fn ($p) => [
                                'id' => $p->id,
                                'precio' => $p->precio,
                            ])->all(),
                        ], $factor);
                        $producto->precio = $data['precio'];
                        $producto->precio_sin_iva = $data['precio_sin_iva'];
                        $producto->precio_con_iva = $data['precio_con_iva'];
                        // ponytail: saveQuietly evita N llamadas Woo/Shopify; techo = catálogo grande sin sync ecommerce.
                        $producto->saveQuietly();
                        foreach ($producto->precios as $i => $fila) {
                            if (! isset($data['precios'][$i]['precio'])) {
                                continue;
                            }
                            $fila->precio = $data['precios'][$i]['precio'];
                            $fila->saveQuietly();
                        }
                        $actualizados++;
                    }
                });

            $empresa->updateCustomConfig('configuraciones', RecalcularPreciosTipoCambio::KEY_VENTA, $rate);
            $empresa->updateCustomConfig('configuraciones', RecalcularPreciosTipoCambio::KEY_CATALOGO, $rate);
        });
        unset($empresa->custom_config);

        $out = $this->snapshot($empresa);
        $out['actualizados'] = $actualizados;

        return $out;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (float) $value;

        return $n > 0 ? $n : null;
    }
}
