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

        $this->assertFalse($this->callProtected($import, 'validarFilaRequeridos', [$fila, 8]));
        $errores = $import->getErrores();
        $this->assertNotEmpty($errores);
        $this->assertSame('descripcion', $errores[0]['columna']);
        $this->assertSame(8, $errores[0]['fila']);
        $this->assertIsString($errores[0]['mensaje']);
    }

    public function test_persona_ccf_sin_nit_reporta_columna_nit(): void
    {
        $import = new VentasExcelImport();
        $fila = $this->filaConsumidorFinalSinDescripcion();
        $fila['descripcion'] = 'Servicio';
        $fila['tipo_documento_venta'] = 'Crédito fiscal';
        $fila['nit'] = '';
        $fila['nrc'] = '';

        $this->assertFalse($this->callProtected($import, 'validarFilaRequeridos', [$fila, 12]));
        $cols = array_column($import->getErrores(), 'columna');
        $this->assertContains('nit', $cols);
        $this->assertContains('nrc', $cols);
    }

    public function test_tipo_documento_usa_columna_no_nit(): void
    {
        $import = new VentasExcelImport();
        $fila = $this->filaConsumidorFinalSinDescripcion();
        $fila['descripcion'] = 'Servicio';
        $fila['nit'] = '0614-010190-001-1';
        $fila['tipo_documento_venta'] = 'Factura';

        $this->assertSame(
            'consumidor_final',
            $this->callProtected($import, 'determinarTipoDocumento', [$fila])
        );

        $fila['tipo_documento_venta'] = 'Crédito fiscal';
        $this->assertSame(
            'credito_fiscal',
            $this->callProtected($import, 'determinarTipoDocumento', [$fila])
        );
    }

    public function test_detalle_siempre_es_servicio(): void
    {
        $import = new VentasExcelImport();
        $detalle = $this->callProtected($import, 'obtenerDatosDetalle', [[
            'descripcion' => 'Item historico',
            'tipo_item' => 'Producto',
            'gravada' => 100,
            'total' => 113,
        ]]);

        $this->assertSame('Servicio', $detalle['tipo_item']);
        $this->assertSame(0, $detalle['id_producto']);
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
            'tipo_cliente' => 'Persona',
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
