<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\CrearCreditoContratoService;
use PHPUnit\Framework\TestCase;

class CrearCreditoContratoServiceTest extends TestCase
{
    public function test_no_emite_dte_ni_crea_venta(): void
    {
        $src = file_get_contents(
            (new \ReflectionClass(CrearCreditoContratoService::class))->getFileName()
        );

        $this->assertStringNotContainsString('Venta::create', $src);
        $this->assertStringNotContainsString('FacturacionService', $src);
        $this->assertStringNotContainsString('Dte', $src);
        $this->assertStringNotContainsString('transmite', $src);
    }
}
