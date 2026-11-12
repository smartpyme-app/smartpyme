<?php

namespace Tests\Unit\Constants;

use App\Constants\ConsignaMovimientoTipoConstants;
use PHPUnit\Framework\TestCase;

class ConsignaMovimientoTipoConstantsTest extends TestCase
{
    public function test_custodia_kardex_incluye_liquidacion_cliente(): void
    {
        $tipos = ConsignaMovimientoTipoConstants::tiposKardexCustodiaCliente();
        $this->assertContains(ConsignaMovimientoTipoConstants::LIQUIDACION_CLIENTE, $tipos);
    }
}
