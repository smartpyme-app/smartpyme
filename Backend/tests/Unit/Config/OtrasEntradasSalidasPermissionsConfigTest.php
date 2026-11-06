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
            'productos.otras_entradas_salidas.entradas.ver',
            config('permissions.PERMISSION_PRODUCTOS.otras_entradas.ver')
        );
        $this->assertSame(
            'productos.otras_entradas_salidas.entradas.crear',
            config('permissions.PERMISSION_PRODUCTOS.otras_entradas.crear')
        );
        $this->assertSame(
            'productos.otras_entradas_salidas.entradas.editar',
            config('permissions.PERMISSION_PRODUCTOS.otras_entradas.editar')
        );
        $this->assertSame(
            'productos.otras_entradas_salidas.salidas.ver',
            config('permissions.PERMISSION_PRODUCTOS.otras_salidas.ver')
        );
        $this->assertSame(
            'productos.otras_entradas_salidas.salidas.crear',
            config('permissions.PERMISSION_PRODUCTOS.otras_salidas.crear')
        );
        $this->assertSame(
            'productos.otras_entradas_salidas.salidas.editar',
            config('permissions.PERMISSION_PRODUCTOS.otras_salidas.editar')
        );
    }
}
