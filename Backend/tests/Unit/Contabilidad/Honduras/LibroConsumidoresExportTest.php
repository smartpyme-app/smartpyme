<?php

namespace Tests\Unit\Contabilidad\Honduras;

use App\Exports\Contabilidad\Honduras\LibroConsumidoresExport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LibroConsumidoresExportTest extends TestCase
{
    public function test_separa_bases_gravadas_15_y_18_por_detalle(): void
    {
        $venta = (object) [
            'fecha' => '2026-07-02',
            'correlativo' => '000-001-01-00000001',
            'exenta' => 10,
            'no_sujeta' => 0,
            'total' => 143,
            'cuenta_a_terceros' => 0,
            'iva' => 18,
            'documento' => (object) ['nombre' => 'Factura', 'resolucion' => 'CAI-123'],
            'detalles' => [
                (object) ['porcentaje_impuesto' => 15, 'gravada' => 100],
                (object) ['porcentaje_impuesto' => 18, 'gravada' => 15],
            ],
        ];

        $row = $this->invokeMapVenta($venta, 1);

        $this->assertSame(100.0, $row['gravadas_15']);
        $this->assertSame(15.0, $row['gravadas_18']);
        $this->assertSame(10.0, $row['exentas']);
        $this->assertSame(143.0, $row['total_ventas']);
        $this->assertSame('CAI-123', $row['cai_no']);
        $this->assertSame('', $row['maquina_registradora']);
        $this->assertSame('000-001-01-00000001', $row['factura_no']);
    }

    public function test_clasifica_solo_factura_y_factura_de_exportacion_como_consumidor_final(): void
    {
        $this->assertContains('Factura', LibroConsumidoresExport::TIPOS_CONSUMIDOR);
        $this->assertContains('Factura de exportación', LibroConsumidoresExport::TIPOS_CONSUMIDOR);
        $this->assertNotContains('Crédito fiscal', LibroConsumidoresExport::TIPOS_CONSUMIDOR);
    }

    public function test_encabezado_excel_usa_ventas_gravadas_con_subcolumnas(): void
    {
        $export = new LibroConsumidoresExport();
        $headings = $export->headings();

        $this->assertSame('Ventas Gravadas', $headings[7]);
        $this->assertSame('', $headings[8]);
        $this->assertSame('N° de Maquina registradora', $headings[4]);
        $this->assertSame('Total Ventas', $headings[9]);
    }

    public function test_usa_cai_de_empresa_cuando_documento_no_tiene_resolucion(): void
    {
        $venta = (object) [
            'fecha' => '2026-07-03',
            'correlativo' => '000-001-01-00000002',
            'exenta' => 0,
            'no_sujeta' => 0,
            'total' => 115,
            'cuenta_a_terceros' => 0,
            'iva' => 15,
            'documento' => (object) ['nombre' => 'Factura', 'resolucion' => null],
            'detalles' => [
                (object) ['porcentaje_impuesto' => 15, 'gravada' => 100],
            ],
        ];

        $row = $this->invokeMapVenta($venta, 1, 1, 'CAI-EMPRESA');

        $this->assertSame('CAI-EMPRESA', $row['cai_no']);
        $this->assertSame(100.0, $row['gravadas_15']);
    }

    public function test_devolucion_se_registra_con_montos_negativos(): void
    {
        $devolucion = (object) [
            'fecha' => '2026-07-10',
            'correlativo' => 'NC-001',
            'sub_total' => 100,
            'exenta' => 0,
            'no_sujeta' => 0,
            'iva' => 15,
            'total' => 115,
            'cuenta_a_terceros' => 0,
            'documento' => (object) ['nombre' => 'Nota de crédito', 'resolucion' => 'CAI-999'],
            'detalles' => [],
        ];

        $row = $this->invokeMapVenta($devolucion, 1, -1);

        $this->assertSame(-100.0, $row['gravadas_15']);
        $this->assertSame(0.0, $row['gravadas_18']);
        $this->assertSame(-115.0, $row['total_ventas']);
        $this->assertSame('CAI-999', $row['cai_no']);
    }

    private function invokeMapVenta(object $registro, int $no, int $mult = 1, string $caiEmpresa = ''): array
    {
        $export = new LibroConsumidoresExport();
        $export->caiEmpresa = $caiEmpresa;
        $method = new ReflectionMethod(LibroConsumidoresExport::class, 'mapVenta');
        $method->setAccessible(true);

        return $method->invoke($export, $registro, $no, $mult);
    }
}
