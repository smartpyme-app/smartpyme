<?php

namespace Tests\Unit\WhatsApp;

use App\Http\Controllers\Api\WhatsApp\WebhookController;
use PHPUnit\Framework\TestCase;

class VeEstadisticasGlobalesTest extends TestCase
{
    public function test_solo_super_admin_de_la_empresa_2_ve_el_global(): void
    {
        $this->assertTrue(WebhookController::veEstadisticasGlobales(2, true));
        $this->assertFalse(WebhookController::veEstadisticasGlobales(2, false));
        $this->assertFalse(WebhookController::veEstadisticasGlobales(13, true));
        $this->assertFalse(WebhookController::veEstadisticasGlobales(5, true));
    }
}
