<?php

namespace Tests\Unit\Models;

use App\Models\Ventas\Clientes\Cliente;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ClienteCorreoFeReceptorTest extends TestCase
{
    public function test_empresa_prefiere_empresa_correo_sobre_correo_de_contacto(): void
    {
        $cliente = $this->clienteSinConstructor();
        $cliente->tipo = 'Empresa';
        $cliente->empresa_correo = ' fe@empresa.cr ';
        $cliente->correo = 'contacto@empresa.cr';

        $this->assertSame('fe@empresa.cr', $cliente->getCorreoFeReceptor());
    }

    public function test_empresa_sin_empresa_correo_usa_correo_contacto_compat(): void
    {
        $cliente = $this->clienteSinConstructor();
        $cliente->tipo = 'Empresa';
        $cliente->correo = 'legacy@empresa.cr';

        $this->assertSame('legacy@empresa.cr', $cliente->getCorreoFeReceptor());
    }

    private function clienteSinConstructor(): Cliente
    {
        return (new ReflectionClass(Cliente::class))->newInstanceWithoutConstructor();
    }
}