<?php

namespace Tests\Unit\Services\Contabilidad;

use App\Exports\Contabilidad\ElSalvador\SujetosExcluidosDteHelper;
use App\Services\Contabilidad\LibroIvaMontosHelper;
use PHPUnit\Framework\TestCase;

class LibroIvaMontosHelperSvTest extends TestCase
{
    public function test_monto_venta_propio_resta_cuenta_terceros(): void
    {
        $venta = (object) ['total' => 113.0, 'cuenta_a_terceros' => 13.0];
        $this->assertSame(100.0, LibroIvaMontosHelper::montoVentaPropioSinCuentaTerceros($venta));
    }

    public function test_devolucion_compra_usa_multiplicador_negativo(): void
    {
        $devolucion = (object) ['id_compra' => 99];
        $compra = (object) ['id' => 1];
        $this->assertSame(-1.0, LibroIvaMontosHelper::multiplicadorDevolucionCompra($devolucion));
        $this->assertSame(1.0, LibroIvaMontosHelper::multiplicadorDevolucionCompra($compra));
    }

    public function test_tipo_documento_devolucion_sin_tipo_es_nota_credito(): void
    {
        $devolucion = (object) ['id_compra' => 1, 'tipo_documento' => null];
        $this->assertSame('Nota de crédito', LibroIvaMontosHelper::tipoDocumentoCompraParaLibro($devolucion));
    }

    public function test_monto_operacion_bruto_sujeto_excluido_usa_sub_total(): void
    {
        $registro = (object) ['sub_total' => 1000.0, 'total' => 900.0, 'renta_retenida' => 100.0];
        $this->assertSame(1000.0, SujetosExcluidosDteHelper::montoOperacionBruto($registro));
    }
}
