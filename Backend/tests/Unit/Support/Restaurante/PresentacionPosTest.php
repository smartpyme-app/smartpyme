<?php

namespace Tests\Unit\Support\Restaurante;

use App\Support\Restaurante\PresentacionPos;
use PHPUnit\Framework\TestCase;

final class PresentacionPosTest extends TestCase
{
    public function test_nombre_mostrar(): void
    {
        $this->assertSame('Cerveza', PresentacionPos::nombreMostrar(null, 'Cerveza'));
        $this->assertSame('330ml (Cerveza)', PresentacionPos::nombreMostrar('330ml', 'Cerveza'));
        $this->assertSame('330ml (Producto)', PresentacionPos::nombreMostrar('330ml', ''));
        $this->assertSame('Producto', PresentacionPos::nombreMostrar('', ''));
    }

    public function test_cantidad_base(): void
    {
        $this->assertSame(6.0, PresentacionPos::cantidadBase(2, 3));
        $this->assertSame(2.0, PresentacionPos::cantidadBase(2, null));
        $this->assertSame(2.0, PresentacionPos::cantidadBase(2, 0));
    }
}
