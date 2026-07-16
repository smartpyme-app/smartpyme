<?php

namespace Tests\Unit\MH;

use App\Models\MH\Concerns\BuildsTributosVenta;
use PHPUnit\Framework\TestCase;

class TributosVentaGateLogicTest extends TestCase
{
    public function test_documento_con_turismo_sin_iva_distingue_ambos_tipos_de_tributo(): void
    {
        $dte = $this->crearDteConTurismoSinIva();

        $this->assertTrue(method_exists($dte, 'documentoTieneIva'));
        $this->assertTrue(method_exists($dte, 'documentoTieneTributosNoIva'));
        $this->assertFalse($dte->tieneIva());
        $this->assertTrue($dte->tieneTributosNoIva());
    }

    public function test_iva_item_es_cero_cuando_hay_turismo_pero_el_documento_no_tiene_iva(): void
    {
        $dte = $this->crearDteConTurismoSinIva();

        $this->assertSame(0.0, $dte->ivaItem(113.0));
    }

    private function crearDteConTurismoSinIva()
    {
        $documento = new class {
            public float $iva = 0.0;
            public $impuestos;

            public function __construct()
            {
                $this->impuestos = collect([
                    (object) [
                        'monto' => 5.0,
                        'impuesto' => (object) [
                            'codigo_mh' => '59',
                            'porcentaje' => 5.0,
                        ],
                    ],
                ]);
            }

            public function impuestos()
            {
                return $this;
            }

            public function relationLoaded(string $relation): bool
            {
                return true;
            }
        };

        $dte = new class($documento) {
            use BuildsTributosVenta;

            public $venta;

            public function __construct($venta)
            {
                $this->venta = $venta;
            }

            public function tieneIva(): bool
            {
                return $this->documentoTieneIva();
            }

            public function tieneTributosNoIva(): bool
            {
                return $this->documentoTieneTributosNoIva();
            }

            public function ivaItem(float $ventaItem): float
            {
                return $this->calcularIvaItemFactura((object) [], $ventaItem);
            }
        };

        return $dte;
    }
}
