<?php

namespace Tests\Unit\Services\Contabilidad;

use App\Services\Contabilidad\LibroContribuyentesFilas;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LibroContribuyentesFilasTest extends TestCase
{
    public function test_nombre_cliente_desde_fila_empresa(): void
    {
        $row = (object) [
            'id_cliente' => 1,
            'cliente_tipo' => 'Empresa',
            'cliente_nombre_empresa' => 'ACME SA',
            'cliente_nombre_comercial' => 'ACME',
        ];

        $nombre = $this->invokeNombreClienteDesdeFila($row);

        $this->assertStringContainsString('ACME', $nombre);
    }

    public function test_nombre_cliente_sin_cliente_es_consumidor_final(): void
    {
        $row = (object) ['id_cliente' => null];

        $this->assertSame('Consumidor Final', $this->invokeNombreClienteDesdeFila($row));
    }

    private function invokeNombreClienteDesdeFila(object $row): string
    {
        $method = new ReflectionMethod(LibroContribuyentesFilas::class, 'nombreClienteDesdeFila');
        $method->setAccessible(true);

        return $method->invoke(null, $row);
    }
}
