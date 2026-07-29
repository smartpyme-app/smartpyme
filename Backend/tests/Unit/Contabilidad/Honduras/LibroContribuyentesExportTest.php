<?php

namespace Tests\Unit\Contabilidad\Honduras;

use App\Exports\Contabilidad\Honduras\LibroContribuyentesExport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LibroContribuyentesExportTest extends TestCase
{
    public function test_mapea_credito_fiscal_y_totales(): void
    {
        $venta = (object) [
            'fecha' => '2026-07-03',
            'correlativo' => 'CCF-1',
            'exenta' => 5,
            'no_sujeta' => 2,
            'gravada' => 100,
            'iva' => 15,
            'cuenta_a_terceros' => 3,
            'iva_percibido' => 1,
            'iva_retenido' => 0.5,
            'total' => 126,
            'nombre_cliente' => 'Cliente HN',
            'cliente' => (object) ['ncr' => '0801', 'nombre' => 'Cliente HN'],
            'documento' => (object) ['nombre' => 'Crédito fiscal'],
        ];

        $row = $this->invokeMapVenta($venta, 1);

        $this->assertSame(1, $row['no']);
        $this->assertSame('2026-07-03', $row['fecha']);
        $this->assertSame('CCF-1', $row['correlativo']);
        $this->assertSame('0801', $row['nrc']);
        $this->assertSame('Cliente HN', $row['nombre']);
        $this->assertSame(5.0, $row['exentas']);
        $this->assertSame(2.0, $row['no_sujetas']);
        $this->assertSame(100.0, $row['gravadas_locales']);
        $this->assertSame(15.0, $row['debito_fiscal']);
        $this->assertSame(3.0, $row['cta_terceros']);
        $this->assertSame(0.0, $row['debito_cta_terceros']);
        $this->assertSame(1.0, $row['iva_percibido']);
        $this->assertSame(0.5, $row['iva_retenido']);
        $this->assertSame(126.0, $row['total']);
    }

    public function test_clasifica_solo_credito_fiscal_como_contribuyente(): void
    {
        $this->assertContains('Crédito fiscal', LibroContribuyentesExport::TIPOS_CONTRIBUYENTE);
        $this->assertNotContains('Factura', LibroContribuyentesExport::TIPOS_CONTRIBUYENTE);
        $this->assertNotContains('Factura de exportación', LibroContribuyentesExport::TIPOS_CONTRIBUYENTE);
    }

    public function test_encabezado_excel_usa_grupo_ventas_y_arranca_en_a7(): void
    {
        $export = new LibroContribuyentesExport();
        $headings = $export->headings();

        $this->assertSame('A7', $export->startCell());
        $this->assertSame('No.', $headings[0]);
        $this->assertSame('Nombre del Contribuyente', $headings[4]);
        $this->assertSame('Ventas', $headings[5]);
        $this->assertSame('', $headings[6]);
        $this->assertCount(14, $headings);
    }

    public function test_devolucion_se_registra_con_montos_negativos(): void
    {
        $devolucion = (object) [
            'fecha' => '2026-07-10',
            'correlativo' => 'NC-001',
            'sub_total' => 100,
            'exenta' => 0,
            'no_sujeta' => 0,
            'gravada' => 100,
            'iva' => 15,
            'total' => 115,
            'cuenta_a_terceros' => 0,
            'iva_percibido' => 0,
            'iva_retenido' => 0,
            'nombre_cliente' => 'Cliente HN',
            'cliente' => (object) ['ncr' => '0801', 'nombre' => 'Cliente HN'],
            'documento' => (object) ['nombre' => 'Nota de crédito'],
        ];

        $row = $this->invokeMapVenta($devolucion, 1, -1);

        $this->assertSame(-100.0, $row['gravadas_locales']);
        $this->assertSame(-15.0, $row['debito_fiscal']);
        $this->assertSame(-115.0, $row['total']);
        $this->assertSame(0.0, $row['debito_cta_terceros']);
    }

    public function test_resumen_operaciones_suma_filas_y_estructura(): void
    {
        $filas = [
            [
                'gravadas_locales' => 100.0,
                'debito_fiscal' => 15.0,
                'cta_terceros' => 3.0,
                'debito_cta_terceros' => 0.0,
                'iva_percibido' => 1.0,
                'iva_retenido' => 0.5,
            ],
            [
                'gravadas_locales' => -20.0,
                'debito_fiscal' => -3.0,
                'cta_terceros' => 0.0,
                'debito_cta_terceros' => 0.0,
                'iva_percibido' => 0.0,
                'iva_retenido' => 0.0,
            ],
        ];

        $resumen = $this->invokeResumenOperaciones($filas);

        $claves = ['gravadas', 'exportaciones', 'debito_fiscal', 'iva_percibido', 'iva_retenido'];
        foreach (['totales_detalle', 'consumidor_final', 'contribuyentes', 'cta_terceros'] as $bloque) {
            $this->assertArrayHasKey($bloque, $resumen);
            foreach ($claves as $clave) {
                $this->assertArrayHasKey($clave, $resumen[$bloque]);
            }
        }

        $this->assertSame(80.0, $resumen['totales_detalle']['gravadas']);
        $this->assertSame(0.0, $resumen['totales_detalle']['exportaciones']);
        $this->assertSame(12.0, $resumen['totales_detalle']['debito_fiscal']);
        $this->assertSame(1.0, $resumen['totales_detalle']['iva_percibido']);
        $this->assertSame(0.5, $resumen['totales_detalle']['iva_retenido']);

        $this->assertSame(80.0, $resumen['contribuyentes']['gravadas']);
        $this->assertSame(12.0, $resumen['contribuyentes']['debito_fiscal']);
        $this->assertSame(1.0, $resumen['contribuyentes']['iva_percibido']);
        $this->assertSame(0.5, $resumen['contribuyentes']['iva_retenido']);
        $this->assertSame(0.0, $resumen['contribuyentes']['exportaciones']);

        // Este libro no consulta Factura; consumidor final queda en cero.
        $this->assertSame(0.0, $resumen['consumidor_final']['gravadas']);
        $this->assertSame(0.0, $resumen['consumidor_final']['debito_fiscal']);

        $this->assertSame(3.0, $resumen['cta_terceros']['gravadas']);
        $this->assertSame(0.0, $resumen['cta_terceros']['debito_fiscal']);
        $this->assertSame(0.0, $resumen['cta_terceros']['exportaciones']);
    }

    private function invokeMapVenta(object $registro, int $no, int $mult = 1): array
    {
        $export = new LibroContribuyentesExport();
        $method = new ReflectionMethod(LibroContribuyentesExport::class, 'mapVenta');
        $method->setAccessible(true);

        return $method->invoke($export, $registro, $no, $mult);
    }

    private function invokeResumenOperaciones(array $filas): array
    {
        $export = new LibroContribuyentesExport();
        $method = new ReflectionMethod(LibroContribuyentesExport::class, 'resumenOperaciones');
        $method->setAccessible(true);

        return $method->invoke($export, $filas);
    }
}
