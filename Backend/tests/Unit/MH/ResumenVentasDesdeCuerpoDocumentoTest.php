<?php

namespace Tests\Unit\MH;

use App\Models\MH\Concerns\BuildsTributosVenta;
use PHPUnit\Framework\TestCase;

class ResumenVentasDesdeCuerpoDocumentoTest extends TestCase
{
    public function test_total_gravada_es_la_suma_de_venta_gravada_del_cuerpo(): void
    {
        $dte = new class {
            use BuildsTributosVenta;

            public function bases(array $cuerpo): array
            {
                return $this->resumenVentasDesdeCuerpoDocumento($cuerpo);
            }
        };

        $bases = $dte->bases([
            ['ventaGravada' => 16.92, 'ventaExenta' => 0, 'ventaNoSuj' => 0],
            ['ventaGravada' => 16.92, 'ventaExenta' => 0, 'ventaNoSuj' => 0],
            ['ventaGravada' => 100.0, 'ventaExenta' => 5.5, 'ventaNoSuj' => 1.25],
        ]);

        $this->assertSame(133.84, $bases['totalGravada']);
        $this->assertSame(5.5, $bases['totalExenta']);
        $this->assertSame(1.25, $bases['totalNoSuj']);
        $this->assertSame(140.59, $bases['subTotalVentas']);
    }

    public function test_ccf_24_lineas_suma_venta_gravada_a_4_decimales_y_calcula_iva_exacto(): void
    {
        $dte = new class {
            use BuildsTributosVenta;

            public $venta;

            public function __construct()
            {
                $this->venta = (object) ['iva' => 235.98];
            }

            protected function filasImpuestosDocumento()
            {
                return collect([
                    (object) [
                        'monto' => 235.98,
                        'impuesto' => (object) [
                            'codigo_mh' => '20',
                            'nombre' => 'IVA',
                            'porcentaje' => 13.0,
                        ],
                    ],
                ]);
            }

            public function resumen(array $cuerpo): array
            {
                $bases = $this->resumenVentasDesdeCuerpoDocumento($cuerpo);
                $tributos = $this->buildTributosResumen($bases['totalGravada']);
                $montoTotalOperacion = $this->montoTotalOperacionConTributos($bases['subTotalVentas'], $tributos, 0.0);

                return [
                    'totalGravada' => $bases['totalGravada'],
                    'subTotalVentas' => $bases['subTotalVentas'],
                    'iva' => $tributos->first()['valor'],
                    'montoTotalOperacion' => $montoTotalOperacion,
                ];
            }
        };

        $cantidades = [
            18, 18, 18, 18, 18, 18, 18, 18, 36, 18, 18, 18,
            270, 90, 90, 144, 18, 18, 18, 18, 18, 36, 36, 720,
        ];
        $precioUni = 1.061947;
        $cuerpo = array_map(function ($cantidad) use ($precioUni) {
            $ventaItem = round($precioUni * $cantidad, 4);
            return [
                'ventaGravada' => floatval(number_format($ventaItem, 4, '.', '')),
                'ventaExenta' => 0.0,
                'ventaNoSuj' => 0.0,
            ];
        }, $cantidades);

        $res = $dte->resumen($cuerpo);

        $this->assertSame(19.115, $cuerpo[0]['ventaGravada']);
        $this->assertSame(1815.93, $res['totalGravada']);
        $this->assertSame(1815.93, $res['subTotalVentas']);
        $this->assertSame(236.07, $res['iva']);
        $this->assertSame(2052.0, $res['montoTotalOperacion']);
    }
}
