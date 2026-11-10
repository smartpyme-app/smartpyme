<?php

namespace Tests\Unit\Models;

use App\Models\Admin\Empresa;
use Mockery;
use Tests\TestCase;

class EmpresaConsignacionesLedgerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ledger_inactivo_sin_funcionalidad(): void
    {
        $empresa = Mockery::mock(Empresa::class)->makePartial();
        $empresa->shouldReceive('tieneFuncionalidadModuloConsignaciones')->andReturn(false);

        $this->assertFalse($empresa->isModuloConsignacionesLedgerActivo());
    }

    public function test_ledger_inactivo_sin_preferencia_mi_cuenta(): void
    {
        $empresa = Mockery::mock(Empresa::class)->makePartial();
        $empresa->id = 1;
        $empresa->shouldReceive('tieneFuncionalidadModuloConsignaciones')->andReturn(true);
        $empresa->shouldReceive('getCustomConfigValue')
            ->with('configuraciones', 'consignaciones_ledger_activo', false)
            ->andReturn(false);

        $this->assertFalse($empresa->isModuloConsignacionesLedgerActivo());
    }

    public function test_ledger_activo_con_funcionalidad_y_preferencia(): void
    {
        $empresa = Mockery::mock(Empresa::class)->makePartial();
        $empresa->id = 1;
        $empresa->shouldReceive('tieneFuncionalidadModuloConsignaciones')->andReturn(true);
        $empresa->shouldReceive('getCustomConfigValue')
            ->with('configuraciones', 'consignaciones_ledger_activo', false)
            ->andReturn(true);

        $this->assertTrue($empresa->isModuloConsignacionesLedgerActivo());
    }

    public function test_tiene_funcionalidad_delega_slug_modulo_consignaciones(): void
    {
        $empresa = Mockery::mock(Empresa::class)->makePartial();
        $empresa->shouldReceive('tieneFuncionalidad')
            ->once()
            ->with('modulo-consignaciones')
            ->andReturn(true);

        $this->assertTrue($empresa->tieneFuncionalidadModuloConsignaciones());
    }
}
