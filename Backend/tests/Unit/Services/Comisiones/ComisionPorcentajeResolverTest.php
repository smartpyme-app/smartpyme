<?php

namespace Tests\Unit\Services\Comisiones;

use App\Services\Comisiones\ComisionPorcentajeResolver;
use PHPUnit\Framework\TestCase;

class ComisionPorcentajeResolverTest extends TestCase
{
    public function test_subcategoria_override_gana_sobre_categoria(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn (int $e, int $c) => 2.0,
            fn (int $e, int $s) => 1.5
        );
        $this->assertSame(1.5, $resolver->resolver(1, 10, 20));
    }

    public function test_usa_categoria_si_no_hay_override(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn (int $e, int $c) => 2.0,
            fn (int $e, int $s) => null
        );
        $this->assertSame(2.0, $resolver->resolver(1, 10, 20));
    }

    public function test_cero_si_sin_config(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn () => null,
            fn () => null
        );
        $this->assertSame(0.0, $resolver->resolver(1, 10, null));
    }
}
