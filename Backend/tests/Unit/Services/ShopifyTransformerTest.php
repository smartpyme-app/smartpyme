<?php

namespace Tests\Unit\Services;

use App\Services\ImpuestosService;
use App\Services\ShopifyTransformer;
use Tests\TestCase;

class ShopifyTransformerTest extends TestCase
{
    private ShopifyTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $impuestosService = $this->createMock(ImpuestosService::class);
        $this->transformer = new ShopifyTransformer($impuestosService);
    }

    public function test_transformar_cliente_con_empresa(): void
    {
        $payload = [
            'id_empresa' => 1,
            'id_usuario' => 1,
            'customer' => [
                'id' => 123456789,
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'email' => 'juan@empresa.com',
                'phone' => '+50370000000',
            ],
            'billing_address' => [
                'company' => 'Acme Corporation S.A.',
                'address1' => 'Calle Principal #123',
                'address2' => 'Edificio Central',
                'city' => 'San Salvador',
                'province' => 'San Salvador',
                'country' => 'El Salvador',
                'country_code' => 'SV',
                'phone' => '+50370000000',
            ],
        ];

        $cliente = $this->transformer->transformarCliente($payload);

        $this->assertSame('Juan', $cliente['nombre']);
        $this->assertSame('Perez', $cliente['apellido']);
        $this->assertSame('Acme Corporation S.A.', $cliente['nombre_empresa']);
        $this->assertSame('Empresa', $cliente['tipo']);
        $this->assertSame(123456789, $cliente['shopify_customer_id']);
        $this->assertSame('juan@empresa.com', $cliente['correo']);
    }

    public function test_transformar_cliente_persona_natural_sin_empresa(): void
    {
        $payload = [
            'id_empresa' => 1,
            'id_usuario' => 1,
            'customer' => [
                'id' => 987654321,
                'first_name' => 'Maria',
                'last_name' => 'Lopez',
                'email' => 'maria@gmail.com',
                'phone' => '+50371111111',
            ],
            'billing_address' => [
                'company' => '',
                'address1' => 'Colonia Escalon',
                'address2' => '',
                'city' => 'San Salvador',
                'province' => 'San Salvador',
                'country' => 'El Salvador',
                'country_code' => 'SV',
                'phone' => '+50371111111',
            ],
        ];

        $cliente = $this->transformer->transformarCliente($payload);

        $this->assertSame('Maria', $cliente['nombre']);
        $this->assertSame('Lopez', $cliente['apellido']);
        $this->assertNull($cliente['nombre_empresa']);
        $this->assertSame('Persona', $cliente['tipo']);
        $this->assertSame(987654321, $cliente['shopify_customer_id']);
    }
}
