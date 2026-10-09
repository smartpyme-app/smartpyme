<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\ComparadorNombreProducto;
use PHPUnit\Framework\TestCase;

class ComparadorNombreProductoTest extends TestCase
{
    public function test_coincide_exacto_ignora_mayusculas_y_espacios(): void
    {
        $this->assertTrue(
            ComparadorNombreProducto::coincideExacto('  SERVICIOS LEGALES  ', 'Servicios legales')
        );
    }

    public function test_no_coincide_textos_distintos(): void
    {
        $this->assertFalse(
            ComparadorNombreProducto::coincideExacto('SERVICIO LEGAL', 'Servicios legales')
        );
    }
}
