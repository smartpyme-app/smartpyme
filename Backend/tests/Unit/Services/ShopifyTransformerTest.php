<?php

namespace Tests\Unit\Services;

use App\Services\ImpuestosService;
use App\Services\ShopifyTransformer;
use Carbon\Carbon;
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
                'province_code' => 'SV-SS',
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
        $this->assertSame('06', $cliente['cod_departamento']);
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
                'province_code' => 'SV-SS',
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
        $this->assertSame('06', $cliente['cod_departamento']);
        $this->assertSame('El Salvador', $cliente['pais']);
    }

    public function test_transformar_cliente_desde_shopify_webhook_santa_ana(): void
    {
        $payload = [
            'id' => 27540943208818,
            'id_empresa' => 553,
            'id_usuario' => 1542,
            'first_name' => 'José',
            'last_name' => 'España',
            'email' => 'joseespana94@gmail.com',
            'phone' => '+50377310933',
            'default_address' => [
                'first_name' => 'JOSE WILFREDO ESPAÑA',
                'last_name' => 'España',
                'company' => null,
                'address1' => 'Colonia el ivu casona xd',
                'address2' => 'suite presidencial xd',
                'city' => 'Santa ana',
                'province' => 'Santa Ana',
                'province_code' => 'SV-SA',
                'country' => 'El Salvador',
                'country_code' => 'SV',
                'phone' => '+50377310932',
            ],
        ];

        $cliente = $this->transformer->transformarClienteDesdeShopify($payload);

        $this->assertSame('José', $cliente['nombre']);
        $this->assertSame('España', $cliente['apellido']);
        $this->assertSame('joseespana94@gmail.com', $cliente['correo']);
        $this->assertSame('+50377310932', $cliente['telefono']);
        $this->assertSame('Colonia el ivu casona xd suite presidencial xd', $cliente['direccion']);
        $this->assertSame('Santa Ana', $cliente['departamento']);
        $this->assertSame('02', $cliente['cod_departamento']);
        $this->assertSame(27540943208818, $cliente['shopify_customer_id']);
        $this->assertSame('Persona', $cliente['tipo']);
    }

    public function test_transformar_cliente_con_telefono_vacio_castea_a_null(): void
    {
        $payload = [
            'id_empresa' => 1,
            'id_usuario' => 1,
            'customer' => [
                'id' => 11223344,
                'first_name' => 'Ana',
                'last_name' => 'Gomez',
                'email' => 'ana@gmail.com',
                'phone' => '', // string vacío
            ],
            'billing_address' => [
                'company' => null,
                'address1' => 'Colonia San Benito',
                'city' => 'San Salvador',
                'province' => 'San Salvador',
                'province_code' => 'SV-SS',
                'country' => 'El Salvador',
                'country_code' => 'SV',
                'phone' => '   ', // whitespace
            ],
        ];

        $cliente = $this->transformer->transformarCliente($payload);

        $this->assertNull($cliente['telefono']);
        $this->assertNull($cliente['empresa_telefono']);
    }

    public function test_fechas_oficiales_desde_pago_usa_el_momento_del_pago_no_el_del_pedido(): void
    {
        $pagadoLunes = Carbon::parse('2026-06-02 09:15:30', 'America/El_Salvador');

        $fechas = $this->transformer->fechasOficialesDesdePago($pagadoLunes);

        $this->assertSame('2026-06-02', $fechas['fecha']);
        $this->assertSame('2026-06-02', $fechas['fecha_pago']);
        $this->assertTrue($pagadoLunes->equalTo($fechas['created_at']));
    }

    public function test_mapear_forma_pago_cod_es_contra_entrega(): void
    {
        $formaPago = $this->transformer->mapearFormaPago([
            'payment_gateway_names' => ['Cash on Delivery (COD)'],
            'financial_status' => 'pending',
        ]);

        $this->assertSame('Contra entrega', $formaPago);
    }

    public function test_mapear_forma_pago_bank_deposit_es_transferencia(): void
    {
        $formaPago = $this->transformer->mapearFormaPago([
            'payment_gateway_names' => ['Bank Deposit'],
            'financial_status' => 'paid',
        ]);

        $this->assertSame('Transferencia', $formaPago);
    }
}
