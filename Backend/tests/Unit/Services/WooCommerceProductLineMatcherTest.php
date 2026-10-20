<?php

namespace Tests\Unit\Services;

use App\Models\Inventario\Producto;
use App\Services\WooCommerceProductLineMatcher;
use App\Services\WooCommerceTallaNombreParser;
use Tests\TestCase;

class WooCommerceProductLineMatcherTest extends TestCase
{
    public function test_variation_sku_for_producto(): void
    {
        $matcher = new WooCommerceProductLineMatcher(new WooCommerceTallaNombreParser());
        $p = new Producto([
            'nombre' => 'ZW-3687P FUCSIA L',
            'codigo' => 'ZW-3687P FUCSIA',
        ]);

        $this->assertSame('ZW-3687P FUCSIA-L', $matcher->variationSkuForProducto($p));
    }

    public function test_remote_skus_try_variation_before_codigo(): void
    {
        $matcher = new WooCommerceProductLineMatcher(new WooCommerceTallaNombreParser());
        $p = new Producto([
            'nombre' => 'CAMISA M',
            'codigo' => 'CAM-01',
        ]);
        $this->assertSame(['CAM-01-M', 'CAM-01'], $matcher->remoteSkusToTry($p));
    }
}
