<?php

namespace Tests\Unit\Services\Comisiones;

use App\Services\Comisiones\ComisionPorcentajeResolver;
use PHPUnit\Framework\TestCase;

class ComisionPorcentajeResolverTest extends TestCase
{
    public function test_subcategoria_override_gana_sobre_categoria(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn (int $e, int $c, ?int $idRegla = null) => 2.0,
            fn (int $e, int $s, ?int $idRegla = null) => 1.5
        );
        $this->assertSame(1.5, $resolver->resolver(1, 10, 20));
    }

    public function test_usa_categoria_si_no_hay_override(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn (int $e, int $c, ?int $idRegla = null) => 2.0,
            fn (int $e, int $s, ?int $idRegla = null) => null
        );
        $this->assertSame(2.0, $resolver->resolver(1, 10, 20));
    }

    public function test_cero_si_sin_config(): void
    {
        $resolver = new ComisionPorcentajeResolver(
            fn (int $e, int $c, ?int $idRegla = null) => null,
            fn (int $e, int $s, ?int $idRegla = null) => null
        );
        $this->assertSame(0.0, $resolver->resolver(1, 10, null));
    }

    public function test_pasa_id_regla_a_lookups(): void
    {
        $catRegla = null;
        $subRegla = null;
        $resolver = new ComisionPorcentajeResolver(
            function (int $e, int $c, ?int $idRegla = null) use (&$catRegla) {
                $catRegla = $idRegla;

                return 2.0;
            },
            function (int $e, int $s, ?int $idRegla = null) use (&$subRegla) {
                $subRegla = $idRegla;

                return null;
            }
        );

        $this->assertSame(2.0, $resolver->resolver(1, 10, 20, 7));
        $this->assertSame(7, $subRegla);
        $this->assertSame(7, $catRegla);
    }

    public function test_sin_id_regla_lookups_reciben_null(): void
    {
        $catRegla = 'unset';
        $resolver = new ComisionPorcentajeResolver(
            function (int $e, int $c, ?int $idRegla = null) use (&$catRegla) {
                $catRegla = $idRegla;

                return 2.0;
            },
            fn (int $e, int $s, ?int $idRegla = null) => null
        );

        $this->assertSame(2.0, $resolver->resolver(1, 10, null));
        $this->assertNull($catRegla);
    }
}
