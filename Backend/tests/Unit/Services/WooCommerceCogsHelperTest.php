<?php

namespace Tests\Unit\Services;

use App\Services\WooCommerceCogsHelper;
use Tests\TestCase;

class WooCommerceCogsHelperTest extends TestCase
{
    public function test_extract_cost_from_order_line_item_format(): void
    {
        $costo = WooCommerceCogsHelper::extractCostFromPayload([
            'cost_of_goods_sold' => ['value' => 40.5],
        ]);

        $this->assertSame(40.5, $costo);
    }

    public function test_extract_cost_from_product_rest_format(): void
    {
        $costo = WooCommerceCogsHelper::extractCostFromPayload([
            'cost_of_goods_sold' => [
                'values' => [
                    ['defined_value' => 65.6, 'effective_value' => 65.6],
                ],
                'total_value' => 65.6,
            ],
        ]);

        $this->assertSame(65.6, $costo);
    }

    public function test_extract_cost_returns_null_when_absent(): void
    {
        $this->assertNull(WooCommerceCogsHelper::extractCostFromPayload([
            'cogs_value' => null,
        ]));
    }

    public function test_build_cost_payload_for_woocommerce_api(): void
    {
        $payload = WooCommerceCogsHelper::buildCostPayload(40.5);

        $this->assertSame([
            'cost_of_goods_sold' => [
                'values' => [
                    ['defined_value' => 40.5],
                ],
            ],
        ], $payload);
    }

    public function test_merge_cost_into_product_data(): void
    {
        $merged = WooCommerceCogsHelper::mergeCostIntoProductData([
            'sku' => 'ABC',
            'price' => '10.00',
        ], 7.25);

        $this->assertSame('ABC', $merged['sku']);
        $this->assertSame(7.25, $merged['cost_of_goods_sold']['values'][0]['defined_value']);
    }
}
