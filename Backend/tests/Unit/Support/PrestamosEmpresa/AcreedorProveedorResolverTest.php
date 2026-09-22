<?php

namespace Tests\Unit\Support\PrestamosEmpresa;

use App\Models\Compras\Proveedores\Proveedor;
use App\Support\PrestamosEmpresa\AcreedorProveedorResolver;
use InvalidArgumentException;
use Tests\TestCase;

class AcreedorProveedorResolverTest extends TestCase
{
    public function test_empresa_se_mapea_a_institucion(): void
    {
        $p = new Proveedor(['tipo' => 'Empresa', 'nombre_empresa' => 'Banco ABC']);
        $p->id = 3;

        $out = AcreedorProveedorResolver::fromProveedor($p);

        $this->assertSame('institucion', $out['tipo_acreedor']);
        $this->assertSame('Banco ABC', $out['acreedor']);
        $this->assertSame(3, $out['id_proveedor']);
    }

    public function test_persona_se_mapea_a_persona(): void
    {
        $p = new Proveedor(['tipo' => 'Persona', 'nombre' => 'Ana', 'apellido' => 'López']);
        $p->id = 5;

        $out = AcreedorProveedorResolver::fromProveedor($p);

        $this->assertSame('persona', $out['tipo_acreedor']);
        $this->assertSame('Ana López', $out['acreedor']);
    }

    public function test_rechaza_proveedor_sin_nombre(): void
    {
        $p = new Proveedor(['tipo' => 'Empresa']);
        $p->id = 1;

        $this->expectException(InvalidArgumentException::class);
        AcreedorProveedorResolver::fromProveedor($p);
    }
}
