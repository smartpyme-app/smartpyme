<?php

namespace Tests\Unit\Services\Bancos;

use PHPUnit\Framework\TestCase;

/**
 * ponytail: regla de signo bancario usada al aprobar cheques.
 */
class BancosMovimientoTipoTest extends TestCase
{
    private function tipoDesdeReferencia(?string $referencia): string
    {
        return in_array($referencia, ['Venta', 'Abono de Venta'], true) ? 'Abono' : 'Cargo';
    }

    public function test_cobros_son_abono(): void
    {
        $this->assertSame('Abono', $this->tipoDesdeReferencia('Venta'));
        $this->assertSame('Abono', $this->tipoDesdeReferencia('Abono de Venta'));
    }

    public function test_pagos_son_cargo(): void
    {
        foreach (['Gasto', 'Abono de Gasto', 'Compra', 'Abono de Compra'] as $ref) {
            $this->assertSame('Cargo', $this->tipoDesdeReferencia($ref));
        }
    }
}
