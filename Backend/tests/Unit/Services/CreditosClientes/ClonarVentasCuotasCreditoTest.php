<?php

namespace Tests\Unit\Services\CreditosClientes;

use App\Services\CreditosClientes\ClonarVentasCuotasCredito;
use PHPUnit\Framework\TestCase;

class ClonarVentasCuotasCreditoTest extends TestCase
{
    public function test_no_pasa_por_facturacion_ni_marca_cuota_facturada(): void
    {
        $src = file_get_contents(
            (new \ReflectionClass(ClonarVentasCuotasCredito::class))->getFileName()
        );

        $this->assertStringNotContainsString('FacturacionService', $src);
        $this->assertStringNotContainsString('VincularCuotaVentaService', $src);
        $this->assertStringNotContainsString('ESTADO_FACTURADA', $src);
        $this->assertStringNotContainsString("increment('correlativo')", $src);
        $this->assertStringContainsString("estado = 'Pendiente'", $src);
    }

    public function test_no_fuerza_id_caja_ni_id_corte_en_el_insert(): void
    {
        $src = file_get_contents(
            (new \ReflectionClass(ClonarVentasCuotasCredito::class))->getFileName()
        );

        $this->assertStringNotContainsString('id_caja = null', $src);
        $this->assertStringNotContainsString('id_corte = null', $src);
    }
}
