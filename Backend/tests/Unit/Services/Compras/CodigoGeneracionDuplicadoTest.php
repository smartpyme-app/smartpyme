<?php

namespace Tests\Unit\Services\Compras;

use App\Services\Compras\CodigoGeneracionDuplicado;
use App\Services\Compras\CodigoGeneracionDuplicadoFinder;
use PHPUnit\Framework\TestCase;

class CodigoGeneracionDuplicadoTest extends TestCase
{
    public function test_normalizar_recorta_y_pasa_a_mayusculas(): void
    {
        $this->assertSame('ABC-123', CodigoGeneracionDuplicado::normalizar('  abc-123  '));
    }

    public function test_normalizar_vacio_o_nulo_es_cadena_vacia(): void
    {
        $this->assertSame('', CodigoGeneracionDuplicado::normalizar(null));
        $this->assertSame('', CodigoGeneracionDuplicado::normalizar('   '));
    }

    public function test_mensaje_compra_sin_referencia(): void
    {
        $dup = new CodigoGeneracionDuplicado(CodigoGeneracionDuplicado::TIPO_COMPRA, 10);

        $this->assertSame(
            'Ya está cargada una compra con ese código de generación.',
            $dup->mensaje()
        );
    }

    public function test_mensaje_gasto_con_referencia(): void
    {
        $dup = new CodigoGeneracionDuplicado(CodigoGeneracionDuplicado::TIPO_GASTO, 22, 'DTE-01');

        $this->assertSame(
            'Ya está cargada un gasto con ese código de generación (referencia DTE-01).',
            $dup->mensaje()
        );
    }

    public function test_encontrar_sin_codigo_o_empresa_no_consulta(): void
    {
        $finder = new CodigoGeneracionDuplicadoFinder();

        $this->assertNull($finder->encontrar(1, null));
        $this->assertNull($finder->encontrar(1, '   '));
        $this->assertNull($finder->encontrar(0, 'ABC-123'));
    }
}
