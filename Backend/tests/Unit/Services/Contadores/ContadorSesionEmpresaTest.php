<?php

namespace Tests\Unit\Services\Contadores;

use App\Services\Contadores\ContadorSesionEmpresa;
use PHPUnit\Framework\TestCase;

class ContadorSesionEmpresaTest extends TestCase
{
    public function test_portal_contadores_conserva_la_empresa_en_la_que_trabaja(): void
    {
        $this->assertTrue(ContadorSesionEmpresa::entradaDesdePortalContadores('https://contadores.smartpyme.site'));
    }

    public function test_app_y_unificado_no_son_portal(): void
    {
        $this->assertFalse(ContadorSesionEmpresa::entradaDesdePortalContadores('https://app-unificado.smartpyme.site'));
        $this->assertFalse(ContadorSesionEmpresa::entradaDesdePortalContadores('https://app.smartpyme.site'));
        $this->assertFalse(ContadorSesionEmpresa::entradaDesdePortalContadores(null));
    }
}
