<?php

namespace Tests\Unit\Services\Contadores;

use App\Services\Contadores\ContadorEmpresaAccesoPermisos;
use PHPUnit\Framework\TestCase;

class ContadorEmpresaAccesoPermisosTest extends TestCase
{
    public function test_tiene_permiso_registrar_y_aprobar(): void
    {
        $permisos = ['ver', 'registrar', 'aprobar'];
        $this->assertTrue(ContadorEmpresaAccesoPermisos::tiene($permisos, 'registrar'));
        $this->assertTrue(ContadorEmpresaAccesoPermisos::tiene($permisos, 'aprobar'));
        $this->assertFalse(ContadorEmpresaAccesoPermisos::tiene(['ver'], 'aprobar'));
    }
}
