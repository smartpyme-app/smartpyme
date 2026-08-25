<?php

namespace Tests\Unit\Imports;

use App\Imports\VentasExcelImport;
use Tests\TestCase;

class VentasExcelImportConsumidorFinalTest extends TestCase
{
    public function test_fila_con_venta_pero_sin_descripcion_no_se_trata_como_vacia(): void
    {
        $import = new VentasExcelImport();
        $fila = $this->filaConsumidorFinalSinDescripcion();

        $this->assertFalse($this->callProtected($import, 'esFilaVacia', [$fila]));
    }

    public function test_descripcion_faltante_se_reporta_como_campo_obligatorio(): void
    {
        $import = new VentasExcelImport();
        $fila = $this->filaConsumidorFinalSinDescripcion();

        $this->assertFalse($this->callProtected($import, 'validarFilaRequeridos', [$fila]));
        $this->assertNotEmpty($import->getErrores());
        $this->assertStringContainsString('descripcion', $import->getErrores()[0]);
        $this->assertStringContainsString('DESARROLLADORA ORIZABA', $import->getErrores()[0]);
        $this->assertStringNotContainsString('No se encontraron ventas válidas', implode("\n", $import->getErrores()));
    }

    public function test_fila_sin_ningun_dato_sigue_siendo_vacia(): void
    {
        $import = new VentasExcelImport();
        $fila = [
            'nombre' => null,
            'fecha' => null,
            'descripcion' => null,
            'total' => null,
        ];

        $this->assertTrue($this->callProtected($import, 'esFilaVacia', [$fila]));
    }

    public function test_descripcion_vacia_usa_valor_por_defecto(): void
    {
        $import = new VentasExcelImport();

        $this->assertSame(
            'Sin descripción',
            $this->callProtected($import, 'descripcionDetalle', [['descripcion' => null]])
        );
        $this->assertSame(
            'Sin descripción',
            $this->callProtected($import, 'descripcionDetalle', [['descripcion' => '   ']])
        );
        $this->assertSame(
            'Producto A',
            $this->callProtected($import, 'descripcionDetalle', [['descripcion' => 'Producto A']])
        );
    }

    private function filaConsumidorFinalSinDescripcion(): array
    {
        return [
            'nombre' => 'DESARROLLADORA ORIZABA, S.A. DE C.V.',
            'tipo_documento' => 'NIT',
            'num_documento' => 6231609241075,
            'departamento' => 'San Salvador',
            'municipio' => 'SAN SALVADOR CENTRO',
            'distrito' => 'SAN SALVADOR',
            'direccion' => 'AV. LAS MANGOLIAS',
            'telefono' => 25272400,
            'correo' => 'cliente@example.com',
            'fecha' => 46028,
            'tipo_documento_venta' => 'Factura',
            'correlativo' => 1,
            'descripcion' => null,
            'forma_pago' => 'Tarjeta de crédito/débito',
            'exenta' => 0,
            'gravada' => 3000,
            'subtotal' => 3000,
            'iva' => 390,
            'iva_retenido' => 0,
            'total' => 3390,
            'condicion' => 'Contado',
            'fecha_pago' => 46066,
        ];
    }

    private function callProtected(object $object, string $method, array $args = [])
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($object, $args);
    }
}
