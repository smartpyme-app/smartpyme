<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ShopifyHelper;
use Tests\TestCase;

class ShopifyHelperTest extends TestCase
{
    public function test_resolver_ubicacion_nacional_con_codigo_provincia(): void
    {
        $ubicacion = ShopifyHelper::resolverUbicacionElSalvador('Santa Ana', 'SV-SA', 'Santa Ana', 'SV');

        $this->assertSame('Santa Ana', $ubicacion['departamento']);
        $this->assertSame('02', $ubicacion['cod_departamento']);
        $this->assertSame('Santa Ana', $ubicacion['municipio']);
    }

    public function test_resolver_ubicacion_extranjera(): void
    {
        $ubicacion = ShopifyHelper::resolverUbicacionElSalvador('Miami', 'FL', 'Florida', 'US');

        $this->assertSame('Florida', $ubicacion['departamento']);
        $this->assertNull($ubicacion['cod_departamento']);
        $this->assertSame('Miami', $ubicacion['municipio']);
        $this->assertNull($ubicacion['cod_municipio']);
        $this->assertNull($ubicacion['distrito']);
        $this->assertNull($ubicacion['cod_distrito']);
    }

    public function test_obtener_cliente_consumidor_final_metodo_estatico_helper(): void
    {
        $this->assertTrue(method_exists(ShopifyHelper::class, 'obtenerClienteConsumidorFinal'));
    }

    public function test_log_escribe_exclusivamente_en_canal_shopify(): void
    {
        $channelMock = \Mockery::mock();
        $channelMock->shouldReceive('info')
            ->once()
            ->with('Test shopify info log', ['order_id' => 123]);

        $channelMock->shouldReceive('error')
            ->once()
            ->with('Test shopify error log', ['error' => 'fail']);

        \Illuminate\Support\Facades\Log::shouldReceive('channel')
            ->with('shopify')
            ->andReturn($channelMock);

        ShopifyHelper::info('Test shopify info log', ['order_id' => 123]);
        ShopifyHelper::error('Test shopify error log', ['error' => 'fail']);
    }

    public function test_log_consolidacion_escribe_en_canal_shopify_consolidacion(): void
    {
        $channelMock = \Mockery::mock();
        $channelMock->shouldReceive('info')
            ->once()
            ->with('Test shopify consolidacion info log', ['direccion' => 'shopify_to_sp']);

        \Illuminate\Support\Facades\Log::shouldReceive('channel')
            ->with('shopify_consolidacion')
            ->andReturn($channelMock);

        ShopifyHelper::logConsolidacion('Test shopify consolidacion info log', ['direccion' => 'shopify_to_sp']);
    }
}
