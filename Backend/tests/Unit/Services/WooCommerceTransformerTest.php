<?php

namespace Tests\Unit\Services;

use App\Services\WooCommerceTransformer;
use Tests\TestCase;

class WooCommerceTransformerTest extends TestCase
{
    private WooCommerceTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new WooCommerceTransformer();
    }

    public function test_mapear_forma_pago_efectivo_cod(): void
    {
        $formaPago = $this->transformer->mapearFormaPago([
            'payment_method' => 'cod',
            'payment_method_title' => 'Efectivo - contra entrega (+4% recargo)',
        ]);

        $this->assertSame('Efectivo', $formaPago);
    }

    public function test_mapear_forma_pago_wompi(): void
    {
        $formaPago = $this->transformer->mapearFormaPago([
            'payment_method' => 'wompi_payment',
            'payment_method_title' => 'Tarjeta de crédito o débito y cuotas sin interés',
        ]);

        $this->assertSame('Tarjeta de crédito/débito', $formaPago);
    }

    public function test_transformar_cliente_orden_5823(): void
    {
        $payload = $this->payloadOrden5823();

        $cliente = $this->transformer->transformarCliente($payload);

        $this->assertSame('David', $cliente['nombre']);
        $this->assertSame('Rivas', $cliente['apellido']);
        $this->assertSame('davidjosriv@gmail.com', $cliente['correo']);
        $this->assertSame('Lourdes Colón', $cliente['municipio']);
        $this->assertSame('SV-LI', $cliente['cod_departamento']);
        $this->assertSame('05499965-9', $cliente['dui']);
        $this->assertStringContainsString('Urbanización Jardines de Cuyagualo', $cliente['direccion']);
        $this->assertStringContainsString('portón negro', $cliente['direccion']);
    }

    public function test_transformar_venta_orden_5823(): void
    {
        $payload = $this->payloadOrden5823();

        $venta = $this->transformer->transformarVenta($payload, 1, 1, 100);

        $this->assertSame('Efectivo', $venta['forma_pago']);
        $this->assertSame('Pagada', $venta['estado']);
        $this->assertSame(88.78, $venta['total']);
        $this->assertSame(82.0, $venta['sub_total']);
    }

    public function test_mapear_estado_completed_a_pagada(): void
    {
        $this->assertSame('Pagada', $this->transformer->mapearEstado('completed'));
        $this->assertSame('Anulada', $this->transformer->mapearEstado('cancelled'));
    }

    private function payloadOrden5823(): array
    {
        return [
            'id' => 5823,
            'status' => 'completed',
            'total' => '88.78',
            'total_tax' => '0.00',
            'discount_total' => '0.00',
            'payment_method' => 'cod',
            'payment_method_title' => 'Efectivo - contra entrega (+4% recargo)',
            'date_created' => '2026-06-18T16:14:51',
            'date_paid' => '2026-06-22T20:46:23',
            'billing' => [
                'first_name' => 'David',
                'last_name' => 'Rivas',
                'address_1' => 'Urbanización Jardines de Cuyagualo bk A psj 5 casa 5',
                'address_2' => 'portón negro, muro repellado con plantas',
                'city' => 'Lourdes Colón',
                'state' => 'SV-LI',
                'country' => 'SV',
                'email' => 'davidjosriv@gmail.com',
                'phone' => '64504214',
            ],
            'meta_data' => [
                ['key' => '_billing_dui_sv', 'value' => '05499965-9'],
                ['key' => '_billing_type_sv', 'value' => 'cf'],
            ],
            'line_items' => [
                [
                    'quantity' => 1,
                    'price' => 82,
                    'subtotal' => '82.00',
                    'total' => '82.00',
                    'total_tax' => '0.00',
                    'name' => 'Xiaomi - Redmi Buds 8 Pro.',
                ],
            ],
            'shipping_lines' => [
                [
                    'method_title' => 'Envío a domicilio (Seguro incluido gratis)',
                    'total' => '3.50',
                    'total_tax' => '0.00',
                ],
            ],
            'fee_lines' => [
                [
                    'name' => 'Cargo por manejo de efectivo',
                    'total' => '3.28',
                    'total_tax' => '0.00',
                ],
            ],
            'id_empresa' => 1,
            'id_usuario' => 1,
            'id_bodega' => 1,
            'id_sucursal' => 1,
            'id_canal' => 1,
        ];
    }
}
