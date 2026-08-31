<?php

namespace Tests\Unit\Services\Bancos;

use App\Services\Bancos\CuentaBancariaResolver;
use Tests\TestCase;

class CuentaBancariaResolverTest extends TestCase
{
    public function test_sin_empresa_no_registra_movimientos_bancarios(): void
    {
        $this->assertFalse(CuentaBancariaResolver::debeRegistrarMovimiento(null));
    }

    public function test_sin_contabilidad_no_registra_movimientos_bancarios(): void
    {
        $empresa = new class {
            public function tieneFuncionalidad(string $slug): bool
            {
                return false;
            }
        };

        $this->assertFalse(CuentaBancariaResolver::debeRegistrarMovimiento($empresa));
    }

    public function test_con_contabilidad_si_registra_movimientos_bancarios(): void
    {
        $empresa = new class {
            public function tieneFuncionalidad(string $slug): bool
            {
                return $slug === 'contabilidad';
            }
        };

        $this->assertTrue(CuentaBancariaResolver::debeRegistrarMovimiento($empresa));
    }
}
