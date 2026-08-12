<?php

namespace Tests\Unit\Services\Moneda;

use App\Services\Moneda\BchTipoCambioClient;
use Tests\TestCase;

final class BchTipoCambioClientTest extends TestCase
{
    public function test_parse_cifras_toma_valor_de_la_fecha_exacta(): void
    {
        $client = new BchTipoCambioClient();
        $rate = $client->parseCifrasResponse([
            ['Fecha' => '2026-08-10T00:00:00Z', 'Valor' => 26.90],
            ['Fecha' => '2026-08-11T00:00:00Z', 'Valor' => 26.9455],
        ], '2026-08-11');

        $this->assertSame(26.9455, $rate);
    }

    public function test_parse_cifras_usa_ultimo_dia_previo_si_no_hay_exacto(): void
    {
        $client = new BchTipoCambioClient();
        $rate = $client->parseCifrasResponse([
            ['Fecha' => '2026-08-09T00:00:00Z', 'Valor' => 26.80],
            ['Fecha' => '2026-08-10T00:00:00Z', 'Valor' => 26.90],
        ], '2026-08-11');

        $this->assertSame(26.90, $rate);
    }

    public function test_parse_cifras_vacio_devuelve_null(): void
    {
        $client = new BchTipoCambioClient();
        $this->assertNull($client->parseCifrasResponse([], '2026-08-11'));
        $this->assertNull($client->parseCifrasResponse(null, '2026-08-11'));
    }

    public function test_resolve_base_url_corrige_portal_developer(): void
    {
        $client = new BchTipoCambioClient();
        $this->assertSame(
            'https://bchapi-am.azure-api.net',
            $client->resolveBaseUrl('https://bchapi-am.developer.azure-api.net')
        );
        $this->assertSame(
            'https://bchapi-am.azure-api.net',
            $client->resolveBaseUrl('https://bchapi-am.azure-api.net/')
        );
    }
}
