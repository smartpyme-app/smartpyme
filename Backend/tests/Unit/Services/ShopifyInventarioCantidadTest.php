<?php

namespace Tests\Unit\Services;

use App\Services\ShopifyApiClient;
use App\Services\ShopifyInventarioCantidad;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyInventarioCantidadTest extends TestCase
{
    public function test_leer_devuelve_on_hand_available_y_committed(): void
    {
        Http::fake([
            'https://tienda.myshopify.com/admin/api/2026-10/graphql.json' => Http::response([
                'data' => [
                    'inventoryItem' => [
                        'inventoryLevel' => [
                            'quantities' => [
                                ['name' => 'on_hand', 'quantity' => 15],
                                ['name' => 'available', 'quantity' => 12],
                                ['name' => 'committed', 'quantity' => 3],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new ShopifyApiClient('https://tienda.myshopify.com', 'shpat_test');
        $cantidades = (new ShopifyInventarioCantidad())->leer($client, 64416012992882, 116424671602);

        $this->assertSame(['on_hand' => 15, 'available' => 12, 'committed' => 3], $cantidades);
    }

    public function test_fijar_on_hand_envia_la_cantidad_previa_para_comparar(): void
    {
        Http::fake([
            'https://tienda.myshopify.com/admin/api/2026-10/graphql.json' => Http::sequence()
                ->push(['data' => ['inventoryItem' => ['inventoryLevel' => ['quantities' => [
                    ['name' => 'on_hand', 'quantity' => 11],
                    ['name' => 'available', 'quantity' => 11],
                    ['name' => 'committed', 'quantity' => 0],
                ]]]]], 200)
                ->push(['data' => ['inventorySetQuantities' => ['userErrors' => []]]], 200),
        ]);

        $client = new ShopifyApiClient('https://tienda.myshopify.com', 'shpat_test');
        $ok = (new ShopifyInventarioCantidad())->fijarOnHand($client, 64416012992882, 116424671602, 15);

        $this->assertTrue($ok);
        Http::assertSent(function ($request) {
            $input = $request['variables']['input'] ?? [];

            return ($input['name'] ?? null) === 'on_hand'
                && ($input['quantities'][0]['quantity'] ?? null) === 15
                && ($input['quantities'][0]['changeFromQuantity'] ?? null) === 11;
        });
    }
}
