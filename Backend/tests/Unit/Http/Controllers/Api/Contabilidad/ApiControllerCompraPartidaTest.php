<?php

namespace Tests\Unit\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Api\Contabilidad\ApiController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ApiControllerCompraPartidaTest extends TestCase
{
    public function test_compra_carga_el_modelo_antes_de_crear_la_partida(): void
    {
        $source = $this->methodSource(ApiController::class, 'compra');

        $find = strpos($source, 'Compra::findOrFail');
        $crear = strpos($source, 'crearPartida');

        $this->assertNotFalse($find, 'Debe cargar el modelo Compra; el Request no tiene referenciaDocumentoContable()');
        $this->assertNotFalse($crear);
        $this->assertLessThan($crear, $find);
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
