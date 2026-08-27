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

    public function test_index_expone_total_con_iva_calculado(): void
    {
        $source = $this->methodSource(PedidoRestauranteController::class, 'index');

        $this->assertNotFalse(
            strpos($source, 'PedidoCanalIvaCalculator::calcular'),
            'El listado debe calcular total con IVA para mostrarlo'
        );
        $this->assertNotFalse(
            strpos($source, "setAttribute('total_con_iva'"),
            'Debe exponer total_con_iva en el payload del listado'
        );
    }

    public function test_imprimir_pasa_desglose_de_iva_a_la_vista(): void
    {
        $source = $this->methodSource(PedidoRestauranteController::class, 'imprimir');

        $this->assertNotFalse(
            strpos($source, 'PedidoCanalIvaCalculator::calcular'),
            'El ticket debe calcular IVA sobre la base sin IVA del pedido'
        );
        $this->assertNotFalse(
            strpos($source, "'ivaDisplay'"),
            'Debe enviar ivaDisplay a la vista del ticket'
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
