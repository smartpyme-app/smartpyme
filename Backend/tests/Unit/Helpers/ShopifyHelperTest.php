<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ShopifyHelper;
use Tests\TestCase;

class ShopifyHelperTest extends TestCase
{
    public function test_resolver_ubicacion_nacional_con_codigo_provincia(): void
    {
        $ubicacion = ShopifyHelper::resolverUbicacionElSalvador('Santa Ana', 'SV-SA', 'Santa Ana', 'SV');

        $this->assertSame('Santa Ana', $ubicacion['departamento']);
        $this->assertSame('02', $ubicacion['cod_departamento']);
        $this->assertSame('Santa Ana', $ubicacion['municipio']);
    }

    public function test_resolver_ubicacion_extranjera(): void
    {
        $ubicacion = ShopifyHelper::resolverUbicacionElSalvador('Miami', 'FL', 'Florida', 'US');

        $this->assertSame('Florida', $ubicacion['departamento']);
        $this->assertNull($ubicacion['cod_departamento']);
        $this->assertSame('Miami', $ubicacion['municipio']);
        $this->assertNull($ubicacion['cod_municipio']);
        $this->assertNull($ubicacion['distrito']);
        $this->assertNull($ubicacion['cod_distrito']);
    }
}
