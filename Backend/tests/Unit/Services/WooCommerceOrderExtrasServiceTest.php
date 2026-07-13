<?php

namespace Tests\Unit\Services;

use App\Services\ImpuestosService;
use App\Services\WooCommerceOrderExtrasService;
use Tests\TestCase;

class WooCommerceOrderExtrasServiceTest extends TestCase
{
    public function test_ignora_envio_y_recargo_con_monto_cero(): void
    {
        $service = new WooCommerceOrderExtrasService(new ImpuestosService());

        $envios = $service->procesarEnvios(
            [['method_title' => 'Gratis', 'total' => '0.00', 'total_tax' => '0.00']],
            1,
            1,
            1,
            1
        );

        $recargos = $service->procesarRecargos(
            [['name' => 'Cargo', 'total' => '0.00', 'total_tax' => '0.00']],
            1,
            1,
            1,
            1
        );

        $this->assertSame([], $envios);
        $this->assertSame([], $recargos);
    }
}
