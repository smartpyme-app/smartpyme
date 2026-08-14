<?php

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\CorteCriterio;
use PHPUnit\Framework\TestCase;

class CorteCriterioTest extends TestCase
{
    public function test_usuario_ventas_no_puede_usar_otra_bodega(): void
    {
        $usuario = (object) ['tipo' => 'Ventas', 'id_bodega' => 7];
        $this->assertSame(7, CorteCriterio::resolverIdBodega($usuario, 99));
    }

    public function test_usuario_ventas_limitado_tampoco_puede_usar_otra_bodega(): void
    {
        $usuario = (object) ['tipo' => 'Ventas Limitado', 'id_bodega' => 3];
        $this->assertSame(3, CorteCriterio::resolverIdBodega($usuario, 99));
    }

    public function test_admin_conserva_la_bodega_solicitada(): void
    {
        $usuario = (object) ['tipo' => 'Administrador', 'id_bodega' => 7];
        $this->assertSame(99, CorteCriterio::resolverIdBodega($usuario, 99));
    }

    public function test_supervisor_conserva_la_bodega_solicitada(): void
    {
        $usuario = (object) ['tipo' => 'Supervisor', 'id_bodega' => 1];
        $this->assertSame(5, CorteCriterio::resolverIdBodega($usuario, 5));
    }

    public function test_sin_id_bodega_en_request_no_fuerza_bodega(): void
    {
        $usuario = (object) ['tipo' => 'Ventas', 'id_bodega' => 7];
        $this->assertNull(CorteCriterio::resolverIdBodega($usuario, null));
        $this->assertNull(CorteCriterio::resolverIdBodega($usuario, ''));
    }
}
