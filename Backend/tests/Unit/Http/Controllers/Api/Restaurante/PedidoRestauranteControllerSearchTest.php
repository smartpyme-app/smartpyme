<?php

namespace Tests\Unit\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Api\Restaurante\PedidoRestauranteController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PedidoRestauranteControllerSearchTest extends TestCase
{
    public function test_index_buscador_usa_columnas_reales_de_cliente(): void
    {
        $source = $this->methodSource(PedidoRestauranteController::class, 'index');

        $this->assertNotFalse(
            strpos($source, "->where('nombre', 'like', \$like)"),
            'Debe buscar por la columna nombre del cliente'
        );
        $this->assertNotFalse(
            strpos($source, "->orWhere('apellido', 'like', \$like)"),
            'Debe buscar por la columna apellido del cliente'
        );
        $this->assertNotFalse(
            strpos($source, "CONCAT(TRIM(nombre), ' ', TRIM(apellido))"),
            'Debe buscar por nombre completo concatenado en SQL'
        );
        $this->assertFalse(
            (bool) preg_match("/where\s*\(\s*'nombre_completo'/", $source),
            'No debe usar nombre_completo en SQL; es un accessor, no columna'
        );
    }

    private function methodSource(string $class, string $method): string
    {
        $ref = new ReflectionMethod($class, $method);
        $file = file($ref->getFileName());
        $start = $ref->getStartLine() - 1;
        $length = $ref->getEndLine() - $start;

        return implode('', array_slice($file, $start, $length));
    }
}
