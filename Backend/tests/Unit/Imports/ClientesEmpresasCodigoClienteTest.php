<?php

namespace Tests\Unit\Imports;

use App\Imports\ClientesEmpresas;
use ReflectionMethod;
use Tests\TestCase;

class ClientesEmpresasCodigoClienteTest extends TestCase
{
    public function test_codigo_cliente_empresa_se_normaliza_desde_alias_de_columna(): void
    {
        $import = new ClientesEmpresas();
        $method = new ReflectionMethod(ClientesEmpresas::class, 'codigoClienteEmpresaDesdeFila');
        $method->setAccessible(true);

        $this->assertSame('ACME-42', $method->invoke($import, ['codigo_cliente_empresa' => ' ACME-42 ']));
        $this->assertSame('X', $method->invoke($import, ['codigo_cliente' => 'X']));
        $this->assertNull($method->invoke($import, ['codigo_cliente_empresa' => '']));
        $this->assertNull($method->invoke($import, []));
    }
}