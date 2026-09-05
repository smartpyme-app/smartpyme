<?php

namespace Tests\Unit\Services\Compras;

use App\Models\Compras\Compra;
use App\Models\Compras\Proveedores\Proveedor;
use App\Services\Compras\PagoMasivoBancoAgricolaService;
use Tests\TestCase;

class PagoMasivoBancoAgricolaServiceTest extends TestCase
{
    private PagoMasivoBancoAgricolaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PagoMasivoBancoAgricolaService();
    }

    public function test_genera_fila_txt_con_cuenta_de_12_y_posicion_3_vacia(): void
    {
        $resultado = $this->service->generar(
            [$this->compraValida(['numero_cuenta' => '1300995009', 'total' => 2477.25, 'referencia' => 'P001-1'])],
            '2026-09-05',
            'txt'
        );

        $this->assertSame(1, $resultado['incluidos']);
        $this->assertSame([], $resultado['omitidos']);
        $this->assertSame('text/plain; charset=UTF-8', $resultado['mime']);
        $this->assertSame('pagos-banco-agricola-2026-09-05.txt', $resultado['filename']);
        $this->assertSame(
            "001300995009;Proveedor 1;;2477.25;P001-1;Pago de proveedor 05-09-2026;jperez@gmail.com",
            rtrim($resultado['contenido'], "\r\n")
        );
    }

    public function test_genera_csv_con_coma_como_delimitador(): void
    {
        $resultado = $this->service->generar(
            [$this->compraValida()],
            '2026-09-05',
            'csv'
        );

        $this->assertSame('text/csv; charset=UTF-8', $resultado['mime']);
        $this->assertSame('pagos-banco-agricola-2026-09-05.csv', $resultado['filename']);
        $this->assertSame(
            '001300995009,Proveedor 1,,2477.25,P001-1,Pago de proveedor 05-09-2026,jperez@gmail.com',
            rtrim($resultado['contenido'], "\r\n")
        );
    }

    public function test_omite_banco_que_no_es_agricola(): void
    {
        $resultado = $this->service->generar(
            [$this->compraValida(['banco' => 'Davivienda'])],
            '2026-09-05',
            'csv'
        );

        $this->assertSame(0, $resultado['incluidos']);
        $this->assertSame('', $resultado['contenido']);
        $this->assertCount(1, $resultado['omitidos']);
        $this->assertSame('El banco no es Banco Agrícola', $resultado['omitidos'][0]['motivo']);
    }

    public function test_acepta_banco_agricola_sin_tilde(): void
    {
        $resultado = $this->service->generar(
            [$this->compraValida(['banco' => 'BANCO AGRICOLA'])],
            '2026-09-05',
            'csv'
        );

        $this->assertSame(1, $resultado['incluidos']);
    }

    public function test_omite_sin_numero_de_cuenta(): void
    {
        $resultado = $this->service->generar(
            [$this->compraValida(['numero_cuenta' => ''])],
            '2026-09-05',
            'csv'
        );

        $this->assertSame(0, $resultado['incluidos']);
        $this->assertSame('Falta el número de cuenta', $resultado['omitidos'][0]['motivo']);
    }

    public function test_limpia_guiones_de_la_cuenta(): void
    {
        $resultado = $this->service->generar(
            [$this->compraValida(['numero_cuenta' => '1300-995-009'])],
            '2026-09-05',
            'txt'
        );

        $this->assertStringStartsWith('001300995009;', $resultado['contenido']);
    }

    public function test_omite_cuenta_con_mas_de_12_digitos(): void
    {
        $resultado = $this->service->generar(
            [$this->compraValida(['numero_cuenta' => '1234567890123'])],
            '2026-09-05',
            'csv'
        );

        $this->assertSame(0, $resultado['incluidos']);
        $this->assertSame('La cuenta debe tener máximo 12 dígitos', $resultado['omitidos'][0]['motivo']);
    }

    public function test_omite_saldo_cero(): void
    {
        $compra = $this->compraValida(['total' => 100]);
        $compra->abonos_sum_total = 100;
        $compra->devoluciones_sum_total = 0;

        $resultado = $this->service->generar([$compra], '2026-09-05', 'csv');

        $this->assertSame(0, $resultado['incluidos']);
        $this->assertSame('El saldo pendiente es 0', $resultado['omitidos'][0]['motivo']);
    }

    public function test_omite_orden_de_compra(): void
    {
        $compra = $this->compraValida();
        $compra->cotizacion = 1;

        $resultado = $this->service->generar([$compra], '2026-09-05', 'csv');

        $this->assertSame(0, $resultado['incluidos']);
        $this->assertSame('Las órdenes de compra no se incluyen', $resultado['omitidos'][0]['motivo']);
    }

    public function test_correo_largo_queda_vacio_pero_la_fila_se_incluye(): void
    {
        $resultado = $this->service->generar(
            [$this->compraValida(['correo' => str_repeat('a', 40) . '@correo.com'])],
            '2026-09-05',
            'txt'
        );

        $this->assertSame(1, $resultado['incluidos']);
        $this->assertStringEndsWith(';', rtrim($resultado['contenido'], "\r\n"));
    }

    public function test_vence_en_fecha_usa_fecha_pago_o_fecha_mas_30(): void
    {
        $conFechaPago = $this->compraValida();
        $conFechaPago->fecha_pago = '2026-09-05';
        $this->assertTrue($this->service->venceEnFecha($conFechaPago, '2026-09-05'));
        $this->assertFalse($this->service->venceEnFecha($conFechaPago, '2026-09-06'));

        $sinFechaPago = $this->compraValida();
        $sinFechaPago->fecha_pago = null;
        $sinFechaPago->fecha = '2026-08-06';
        $this->assertTrue($this->service->venceEnFecha($sinFechaPago, '2026-09-05'));
    }

    public function test_sanitiza_delimitadores_en_nombre_y_factura(): void
    {
        $resultado = $this->service->generar(
            [$this->compraValida(['nombre_empresa' => 'Proveedor, 1; SA', 'referencia' => 'P001,1'])],
            '2026-09-05',
            'csv'
        );

        $this->assertSame(
            '001300995009,Proveedor 1 SA,,2477.25,P001 1,Pago de proveedor 05-09-2026,jperez@gmail.com',
            rtrim($resultado['contenido'], "\r\n")
        );
    }

    private function compraValida(array $overrides = []): Compra
    {
        $proveedor = new Proveedor();
        $proveedor->tipo = 'Empresa';
        $proveedor->nombre_empresa = $overrides['nombre_empresa'] ?? 'Proveedor 1';
        $proveedor->banco = $overrides['banco'] ?? 'Banco Agrícola';
        $proveedor->numero_cuenta = $overrides['numero_cuenta'] ?? '1300995009';
        $proveedor->correo = $overrides['correo'] ?? 'jperez@gmail.com';

        $compra = new Compra();
        $compra->estado = 'Pendiente';
        $compra->cotizacion = 0;
        $compra->total = $overrides['total'] ?? 2477.25;
        $compra->referencia = $overrides['referencia'] ?? 'P001-1';
        $compra->tipo_documento = 'CCF';
        $compra->fecha = '2026-08-06';
        $compra->fecha_pago = '2026-09-05';
        $compra->abonos_sum_total = 0;
        $compra->devoluciones_sum_total = 0;
        $compra->setRelation('proveedor', $proveedor);

        return $compra;
    }
}
