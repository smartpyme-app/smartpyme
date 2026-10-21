<?php

namespace Tests\Unit\Services;

use App\Services\WooCommerceRemoteIdGuard;
use Exception;
use Tests\TestCase;

class WooCommerceRemoteIdGuardTest extends TestCase
{
    public function test_detects_invalid_product_id_error(): void
    {
        $e = new Exception('Error en petición a WooCommerce API: 400 - {"code":"woocommerce_rest_product_invalid_id"}');
        $this->assertTrue(WooCommerceRemoteIdGuard::isMissingRemoteProduct($e));
    }

    public function test_ignores_unrelated_errors(): void
    {
        $e = new Exception('timeout');
        $this->assertFalse(WooCommerceRemoteIdGuard::isMissingRemoteProduct($e));
    }
}
