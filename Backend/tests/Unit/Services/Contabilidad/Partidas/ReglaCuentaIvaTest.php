<?php

namespace Tests\Unit\Services\Contabilidad\Partidas;

use App\Services\Contabilidad\Partidas\ReglaCuentaIva;
use PHPUnit\Framework\TestCase;

class ReglaCuentaIvaTest extends TestCase
{
    private function config(array $over = []): object
    {
        return (object) array_merge([
            'separar_cuentas_iva' => false,
            'id_cuenta_iva_ventas' => 10,
            'id_cuenta_iva_ventas_cf' => 11,
            'id_cuenta_iva_compras' => 20,
            'id_cuenta_iva_compras_cf' => 21,
        ], $over);
    }

    public function test_tipo_de_prioriza_nombre_documento(): void
    {
        $doc = (object) [
            'nombre_documento' => 'Factura',
            'tipo_documento' => 'CCF',
            'documento' => (object) ['nombre' => 'Crédito fiscal'],
        ];

        $this->assertSame('Factura', ReglaCuentaIva::tipoDe($doc));
    }

    public function test_tipo_de_usa_relacion_documento(): void
    {
        $doc = (object) [
            'documento' => (object) ['nombre' => 'Crédito fiscal'],
            'tipo_documento' => 'Factura',
        ];

        $this->assertSame('Crédito fiscal', ReglaCuentaIva::tipoDe($doc));
    }

    public function test_tipo_de_usa_tipo_documento(): void
    {
        $this->assertSame('CCF', ReglaCuentaIva::tipoDe((object) ['tipo_documento' => 'CCF']));
        $this->assertSame('', ReglaCuentaIva::tipoDe((object) []));
    }

    public function test_es_credito_fiscal(): void
    {
        $this->assertTrue(ReglaCuentaIva::esCreditoFiscal('Crédito fiscal'));
        $this->assertTrue(ReglaCuentaIva::esCreditoFiscal('CCF'));
        $this->assertTrue(ReglaCuentaIva::esCreditoFiscal('ccf'));
        $this->assertTrue(ReglaCuentaIva::esCreditoFiscal('Comprobante de crédito fiscal'));
        $this->assertFalse(ReglaCuentaIva::esCreditoFiscal('Factura'));
        $this->assertFalse(ReglaCuentaIva::esCreditoFiscal('Ticket'));
        $this->assertFalse(ReglaCuentaIva::esCreditoFiscal('Nota de crédito'));
        $this->assertFalse(ReglaCuentaIva::esCreditoFiscal(''));
    }

    public function test_split_apagado_siempre_cuenta_general(): void
    {
        $c = $this->config();

        $this->assertSame(10, ReglaCuentaIva::idCuentaVentas($c, 'Factura'));
        $this->assertSame(10, ReglaCuentaIva::idCuentaVentas($c, 'Crédito fiscal'));
        $this->assertSame(20, ReglaCuentaIva::idCuentaCompras($c, 'CCF'));
        $this->assertSame(20, ReglaCuentaIva::idCuentaCompras($c, 'Factura'));
    }

    public function test_split_encendido_elige_por_tipo(): void
    {
        $c = $this->config(['separar_cuentas_iva' => true]);

        $this->assertSame(10, ReglaCuentaIva::idCuentaVentas($c, 'Crédito fiscal'));
        $this->assertSame(11, ReglaCuentaIva::idCuentaVentas($c, 'Factura'));
        $this->assertSame(11, ReglaCuentaIva::idCuentaVentas($c, 'Ticket'));
        $this->assertSame(11, ReglaCuentaIva::idCuentaVentas($c, 'Nota de crédito'));
        $this->assertSame(20, ReglaCuentaIva::idCuentaCompras($c, 'CCF'));
        $this->assertSame(21, ReglaCuentaIva::idCuentaCompras($c, 'Factura'));
    }

    public function test_split_sin_cuenta_cf_hace_fallback(): void
    {
        $c = $this->config([
            'separar_cuentas_iva' => true,
            'id_cuenta_iva_ventas_cf' => null,
            'id_cuenta_iva_compras_cf' => null,
        ]);

        $this->assertSame(10, ReglaCuentaIva::idCuentaVentas($c, 'Factura'));
        $this->assertSame(20, ReglaCuentaIva::idCuentaCompras($c, 'Ticket'));
    }
}
