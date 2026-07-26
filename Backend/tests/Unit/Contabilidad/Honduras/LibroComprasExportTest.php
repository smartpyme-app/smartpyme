<?php

namespace Tests\Unit\Contabilidad\Honduras;

use App\Exports\Contabilidad\Honduras\LibroComprasExport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LibroComprasExportTest extends TestCase
{
    public function test_mapea_compra_gravada_en_columnas_oficiales(): void
    {
        $registro = (object) [
            'fecha' => '2026-07-01',
            'referencia' => 'FAC-001',
            'tipo_documento' => 'Crédito fiscal',
            'nombre_proveedor' => 'Proveedor HN',
            'sub_total' => 100.0,
            'iva' => 15.0,
            'total' => 115.0,
            'percepcion' => 2.0,
            'iva_retenido' => 1.0,
            'proveedor' => (object) ['ncr' => '08011999123456', 'nit' => 'NIT-1', 'dui' => null],
        ];

        $row = $this->invokeMap((object) ['registro' => $registro, 'mult' => 1], 1);

        $this->assertSame(1, $row['no']);
        $this->assertSame('2026-07-01', $row['fecha_emision']);
        $this->assertSame('FAC-001', $row['numero_documento']);
        $this->assertSame('08011999123456', $row['nrc']);
        $this->assertSame('NIT-1', $row['nit_o_dui']);
        $this->assertSame('Proveedor HN', $row['nombre_proveedor']);
        $this->assertSame(100.0, $row['gravadas_internas']);
        $this->assertSame(0.0, $row['gravadas_importaciones']);
        $this->assertSame(15.0, $row['credito_fiscal']);
        $this->assertSame(0.0, $row['exentas_internaciones']);
        $this->assertSame(0.0, $row['fovial']);
        $this->assertSame(0.0, $row['cotrans']);
        $this->assertSame(0.0, $row['cesc']);
        $this->assertSame(2.0, $row['anticipo_iva_percibido']);
        $this->assertSame(115.0, $row['total']);
        $this->assertSame(1.0, $row['retencion_terceros']);
        $this->assertSame(0.0, $row['compras_sujetos_excluidos']);
    }

    public function test_mapea_importacion_en_columnas_de_importaciones(): void
    {
        $registro = (object) [
            'fecha' => '2026-07-02',
            'referencia' => 'IMP-001',
            'tipo_documento' => 'Importación',
            'nombre_proveedor' => 'Proveedor Ext',
            'sub_total' => 200.0,
            'iva' => 30.0,
            'total' => 230.0,
            'percepcion' => 0.0,
            'iva_retenido' => 0.0,
            'proveedor' => (object) ['ncr' => '', 'nit' => 'NIT-2', 'dui' => null],
        ];

        $row = $this->invokeMap((object) ['registro' => $registro, 'mult' => 1], 2);

        $this->assertSame('IMP-001', $row['numero_documento']);
        $this->assertSame(0.0, $row['gravadas_internas']);
        $this->assertSame(0.0, $row['exentas_internas']);
        $this->assertSame(200.0, $row['gravadas_importaciones']);
        $this->assertSame(30.0, $row['credito_fiscal']);
        $this->assertSame(0.0, $row['exentas_internaciones']);
        $this->assertSame(0.0, $row['gravadas_internaciones']);
        $this->assertSame(0.0, $row['fovial']);
        $this->assertSame(0.0, $row['cotrans']);
        $this->assertSame(0.0, $row['cesc']);
    }

    public function test_mapea_sujeto_excluido_en_columna_dedicada(): void
    {
        $registro = (object) [
            'fecha' => '2026-07-03',
            'referencia' => 'SE-001',
            'tipo_documento' => 'Sujeto excluido',
            'nombre_proveedor' => 'Proveedor Excluido',
            'sub_total' => 50.0,
            'iva' => 0.0,
            'total' => 50.0,
            'percepcion' => 0.0,
            'iva_retenido' => 0.0,
            'proveedor' => (object) ['ncr' => '', 'nit' => null, 'dui' => '0801-1990-12345'],
        ];

        $row = $this->invokeMap((object) ['registro' => $registro, 'mult' => 1], 3);

        $this->assertSame('0801-1990-12345', $row['nit_o_dui']);
        $this->assertSame(50.0, $row['compras_sujetos_excluidos']);
        $this->assertSame(0.0, $row['gravadas_internas']);
        $this->assertSame(0.0, $row['exentas_internas']);
        $this->assertSame(0.0, $row['credito_fiscal']);
        $this->assertSame(0.0, $row['exentas_internaciones']);
        $this->assertSame(0.0, $row['fovial']);
        $this->assertSame(0.0, $row['cotrans']);
        $this->assertSame(0.0, $row['cesc']);
    }

    public function test_aplica_multiplicador_negativo_en_devolucion(): void
    {
        $registro = (object) [
            'fecha' => '2026-07-04',
            'referencia' => 'NC-001',
            'tipo_documento' => 'Crédito fiscal',
            'nombre_proveedor' => 'Proveedor HN',
            'sub_total' => 100.0,
            'iva' => 15.0,
            'total' => 115.0,
            'percepcion' => 2.0,
            'iva_retenido' => 1.0,
            'proveedor' => (object) ['ncr' => '08011999123456', 'nit' => 'NIT-1', 'dui' => null],
        ];

        $row = $this->invokeMap((object) ['registro' => $registro, 'mult' => -1], 4);

        $this->assertSame(-100.0, $row['gravadas_internas']);
        $this->assertSame(-15.0, $row['credito_fiscal']);
        $this->assertSame(-2.0, $row['anticipo_iva_percibido']);
        $this->assertSame(-115.0, $row['total']);
        $this->assertSame(-1.0, $row['retencion_terceros']);
        $this->assertSame(0.0, $row['exentas_internaciones']);
        $this->assertSame(0.0, $row['fovial']);
        $this->assertSame(0.0, $row['cotrans']);
        $this->assertSame(0.0, $row['cesc']);
    }

    public function test_gasto_usa_iva_percibido_cuando_no_hay_percepcion(): void
    {
        $registro = (object) [
            'fecha' => '2026-07-05',
            'referencia' => 'GAS-001',
            'tipo_documento' => 'Crédito fiscal',
            'nombre_proveedor' => 'Proveedor Gasto',
            'sub_total' => 80.0,
            'iva' => 12.0,
            'total' => 92.0,
            'iva_percibido' => 3.5,
            'iva_retenido' => 0.0,
            'proveedor' => (object) ['ncr' => '0801', 'nit' => 'NIT-G', 'dui' => null],
        ];

        $row = $this->invokeMap((object) ['registro' => $registro, 'mult' => 1], 5);

        $this->assertSame(3.5, $row['anticipo_iva_percibido']);
    }

    private function invokeMap(object $item, int $no): array
    {
        $export = new LibroComprasExport();
        $method = new ReflectionMethod(LibroComprasExport::class, 'mapItemToAssoc');
        $method->setAccessible(true);

        return $method->invoke($export, $item, $no);
    }
}
