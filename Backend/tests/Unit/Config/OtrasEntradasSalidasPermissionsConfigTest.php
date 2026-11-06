<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * SPT-648: claves en config/permissions.php para submódulos otras_entradas / otras_salidas.
 */
class OtrasEntradasSalidasPermissionsConfigTest extends TestCase
{
    public function test_nombres_de_permisos_coinciden_con_esquema_productos(): void
    {
        $this->assertSame(
            'productos.otras_entradas_salidas.entradas.crear',
            config('permissions.PERMISSION_PRODUCTOS.otras_entradas.crear')
        );
        $this->assertSame(
            'productos.otras_entradas_salidas.salidas.crear',
            config('permissions.PERMISSION_PRODUCTOS.otras_salidas.crear')
        );
    }
}
