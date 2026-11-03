<?php

namespace Tests\Unit\Support;

use App\Support\PermisoAnular;
use PHPUnit\Framework\TestCase;

class PermisoAnularTest extends TestCase
{
    public function test_eliminar_o_anular_concatena_ambos(): void
    {
        $this->assertSame(
            'ventas.abonos.eliminar|ventas.abonos.anular',
            PermisoAnular::eliminarOAnular('ventas.abonos.eliminar')
        );
    }
}
