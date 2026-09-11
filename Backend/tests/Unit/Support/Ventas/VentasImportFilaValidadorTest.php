<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\VentasImportFilaValidador;
use PHPUnit\Framework\TestCase;

class VentasImportFilaValidadorTest extends TestCase
{
    private VentasImportFilaValidador $v;

    protected function setUp(): void
    {
        parent::setUp();
        $this->v = new VentasImportFilaValidador();
    }

    private function fila(array $over = []): array
    {
        return array_merge([
            'tipo_cliente' => 'Persona',
            'tipo_documento_venta' => 'Factura',
            'correlativo' => 10,
            'nombre' => 'Juan Perez',
            'tipo_documento' => 'DUI',
            'num_documento' => '05027470-7',
            'fecha' => '2025-02-03',
            'descripcion' => 'Servicio A',
            'forma_pago' => 'Efectivo',
            'total' => 113,
            'condicion' => 'Contado',
        ], $over);
    }

    public function test_encabezados_viejos_error_de_archivo(): void
    {
        $err = $this->v->validarEncabezados(['nombre', 'nit', 'fecha']);
        $this->assertNotEmpty($err);
        $this->assertSame('tipo_cliente', $err[0]['columna']);
        $this->assertSame(1, $err[0]['fila']);
    }

    public function test_persona_factura_valida(): void
    {
        $this->assertSame([], $this->v->validarFila($this->fila(), 2));
    }

    public function test_persona_ccf_sin_nit_nrc(): void
    {
        $err = $this->v->validarFila($this->fila([
            'tipo_documento_venta' => 'Crédito fiscal',
            'nit' => '',
            'nrc' => '',
        ]), 12);
        $cols = array_column($err, 'columna');
        $this->assertContains('nit', $cols);
        $this->assertContains('nrc', $cols);
        $this->assertSame(12, $err[0]['fila']);
        $this->assertStringContainsString('Crédito fiscal', $err[0]['mensaje']);
    }

    public function test_persona_ccf_con_nit_nrc_ok(): void
    {
        $this->assertSame([], $this->v->validarFila($this->fila([
            'tipo_documento_venta' => 'Crédito fiscal',
            'nit' => '0614-010190-001-1',
            'nrc' => '123456-7',
        ]), 3));
    }

    public function test_sin_correlativo(): void
    {
        $err = $this->v->validarFila($this->fila(['correlativo' => '']), 5);
        $this->assertSame('correlativo', $err[0]['columna']);
        $this->assertSame(5, $err[0]['fila']);
    }

    public function test_forma_pago_invalida(): void
    {
        $err = $this->v->validarFila($this->fila(['forma_pago' => 'PayPal']), 4);
        $this->assertSame('forma_pago', $err[0]['columna']);
    }

    public function test_agrupa_mismo_correlativo(): void
    {
        $a = $this->fila(['descripcion' => 'A']);
        $b = $this->fila(['descripcion' => 'B']);
        $this->assertSame($this->v->claveAgrupacion($a), $this->v->claveAgrupacion($b));
        $this->assertSame([], $this->v->validarAgrupacion([
            array_merge($a, ['fila' => 2]),
            array_merge($b, ['fila' => 3]),
        ]));
    }

    public function test_mismo_correlativo_dos_clientes(): void
    {
        $err = $this->v->validarAgrupacion([
            array_merge($this->fila(), ['fila' => 2]),
            array_merge($this->fila(['num_documento' => '11111111-1', 'nombre' => 'Otro']), ['fila' => 3]),
        ]);
        $this->assertNotEmpty($err);
        $this->assertSame('correlativo', $err[0]['columna']);
    }

    public function test_tipo_item_siempre_servicio(): void
    {
        $this->assertSame('Servicio', $this->v->tipoItemDetalle());
    }

    public function test_extranjero_factura_exportacion_valida(): void
    {
        $this->assertSame([], $this->v->validarFila($this->fila([
            'tipo_cliente' => 'Extranjero',
            'tipo_documento_venta' => 'Factura de exportación',
            'nombre' => 'John',
            'apellido' => 'Doe',
            'tipo_documento' => 'Pasaporte',
            'num_documento' => 'AB123456',
            'pais' => 'Estados Unidos',
        ]), 2));
    }

    public function test_extranjero_sin_pais(): void
    {
        $err = $this->v->validarFila($this->fila([
            'tipo_cliente' => 'Extranjero',
            'tipo_documento' => 'Pasaporte',
            'num_documento' => 'AB123456',
            'pais' => '',
        ]), 4);
        $this->assertSame('pais', $err[0]['columna']);
    }

    public function test_persona_con_tipo_documento_nit_rechazado(): void
    {
        $err = $this->v->validarFila($this->fila([
            'tipo_documento' => 'NIT',
            'num_documento' => '0614-010190-001-1',
        ]), 6);
        $cols = array_column($err, 'columna');
        $this->assertContains('tipo_documento', $cols);
    }

    public function test_tipo_cliente_extranjero(): void
    {
        $fila = ['tipo_cliente' => 'Extranjero'];
        $this->assertSame('Extranjero', $this->v->tipoCliente($fila));
    }

    public function test_agrupa_extranjero_por_pais_y_documento(): void
    {
        $a = $this->fila([
            'tipo_cliente' => 'Extranjero',
            'tipo_documento_venta' => 'Factura de exportación',
            'tipo_documento' => 'Pasaporte',
            'num_documento' => 'X1',
            'pais' => 'Guatemala',
        ]);
        $b = $this->fila([
            'tipo_cliente' => 'Extranjero',
            'tipo_documento_venta' => 'Factura de exportación',
            'tipo_documento' => 'Pasaporte',
            'num_documento' => 'X1',
            'pais' => 'Guatemala',
            'descripcion' => 'Otro item',
        ]);
        $this->assertSame($this->v->claveAgrupacion($a), $this->v->claveAgrupacion($b));
    }
}
