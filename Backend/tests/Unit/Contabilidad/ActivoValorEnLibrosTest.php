<?php

namespace Tests\Unit\Contabilidad;

use App\Models\Contabilidad\Activo;
use PHPUnit\Framework\TestCase;

class ActivoValorEnLibrosTest extends TestCase
{
    public function test_recalcular_valor_en_libros_no_negativo(): void
    {
        $activo = new Activo([
            'valor_compra' => 1000,
            'depreciacion_acumulada' => 250,
        ]);

        $activo->recalcularValorEnLibros();

        $this->assertSame(750.0, (float) $activo->valor_en_libros);
    }

    public function test_recalcular_valor_en_libros_piso_cero(): void
    {
        $activo = new Activo([
            'valor_compra' => 100,
            'depreciacion_acumulada' => 150,
        ]);

        $activo->recalcularValorEnLibros();

        $this->assertSame(0.0, (float) $activo->valor_en_libros);
    }
}
