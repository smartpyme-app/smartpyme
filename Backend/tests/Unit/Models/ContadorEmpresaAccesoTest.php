<?php

namespace Tests\Unit\Models;

use App\Models\Contadores\ContadorEmpresaAcceso;
use PHPUnit\Framework\TestCase;

class ContadorEmpresaAccesoTest extends TestCase
{
    public function test_permisos_por_defecto_incluyen_aprobar(): void
    {
        $permisos = ContadorEmpresaAcceso::permisosPorDefecto();

        $this->assertContains('ver', $permisos);
        $this->assertContains('aprobar', $permisos);
    }
}
