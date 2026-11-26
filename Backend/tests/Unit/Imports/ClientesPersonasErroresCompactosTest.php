<?php

namespace Tests\Unit\Imports;

use App\Imports\ClientesPersonas;
use ReflectionClass;
use Tests\TestCase;

class ClientesPersonasErroresCompactosTest extends TestCase
{
    public function test_agrupa_dui_existentes_repetidos(): void
    {
        $import = new ClientesPersonas();
        $ref = new ReflectionClass($import);
        $prop = $ref->getProperty('errores');
        $prop->setAccessible(true);
        $prop->setValue($import, [
            'Ya existe un cliente con el DUI: 06031550-4 (Fila: 2)',
            'Ya existe un cliente con el DUI: 01626722-0 (Fila: 3)',
            'Ya existe un cliente con el DUI: 06031550-4 (Fila: 10)',
            'Ya existe un cliente con el DUI: 06031550-4 (Fila: 11)',
        ]);

        $this->assertSame(4, $import->getFallidosCount());
        $compactos = $import->getErrores();
        $this->assertCount(2, $compactos);
        $this->assertSame('Ya existe un cliente con el DUI: 01626722-0 (Fila: 3)', $compactos[1]);
        $this->assertStringContainsString('06031550-4 (3 filas:', $compactos[0]);
    }
}
