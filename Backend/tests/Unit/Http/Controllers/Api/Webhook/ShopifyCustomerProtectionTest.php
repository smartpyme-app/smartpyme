<?php

namespace Tests\Unit\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Ventas\Clientes\Cliente;
use App\Services\ImpuestosService;
use App\Services\ShippingService;
use App\Services\ShopifySyncCache;
use App\Services\ShopifyTransformer;
use ReflectionClass;
use Tests\TestCase;

class ShopifyCustomerProtectionTest extends TestCase
{
    private ShopifyController $controller;
    private $methodActualizarCliente;

    protected function setUp(): void
    {
        parent::setUp();

        $transformer = $this->createMock(ShopifyTransformer::class);
        $cache = $this->createMock(ShopifySyncCache::class);
        $shippingService = $this->createMock(ShippingService::class);
        $impuestosService = $this->createMock(ImpuestosService::class);

        $this->controller = new ShopifyController($transformer, $cache, $shippingService, $impuestosService);

        $reflector = new ReflectionClass(ShopifyController::class);
        $this->methodActualizarCliente = $reflector->getMethod('actualizarClienteExistenteDesdeShopify');
        $this->methodActualizarCliente->setAccessible(true);
    }

    public function test_no_sobreescribe_datos_fiscales_de_cliente_existente(): void
    {
        $cliente = new Cliente([
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'nombre_empresa' => 'Mi Empresa S.A. de C.V.',
            'tipo' => 'Empresa',
            'ncr' => '123456-7',
            'nit' => '0614-010190-001-1',
            'dui' => '01234567-8',
            'giro' => 'Venta de repuestos y accesorios',
            'cod_giro' => '45300',
            'tipo_contribuyente' => 'Mediano',
            'correo' => 'juan@miempresa.com',
            'telefono' => '2222-3333',
            'direccion' => 'Calle Los Próceres #10',
            'departamento' => 'San Salvador',
            'cod_departamento' => '06',
            'municipio' => 'San Salvador Centro',
            'cod_municipio' => '0614',
            'shopify_customer_id' => 999888777,
        ]);

        // Simular que entra un nuevo pedido desde Shopify para este cliente con datos básicos o vacíos
        $shopifyData = [
            'nombre' => 'Juan Carlos',
            'apellido' => 'Perez Gomez',
            'nombre_empresa' => null, // vacío en checkout
            'tipo' => 'Persona',       // default
            'telefono' => '7777-8888',
            'correo' => 'juan@miempresa.com',
            'direccion' => 'Colonia Nueva #456',
            'departamento' => 'La Libertad',
            'cod_departamento' => '05',
            'municipio' => 'Santa Tecla',
            'cod_municipio' => '0501',
            'pais' => 'El Salvador',
            'cod_pais' => 'SV',
            'shopify_customer_id' => 999888777,
        ];

        $resultado = $this->methodActualizarCliente->invoke($this->controller, $cliente, $shopifyData);

        // Los datos fiscales y de empresa deben permanecer INTACTOS
        $this->assertSame('Mi Empresa S.A. de C.V.', $resultado->nombre_empresa);
        $this->assertSame('Empresa', $resultado->tipo);
        $this->assertSame('123456-7', $resultado->ncr);
        $this->assertSame('0614-010190-001-1', $resultado->nit);
        $this->assertSame('01234567-8', $resultado->dui);
        $this->assertSame('Venta de repuestos y accesorios', $resultado->giro);
        $this->assertSame('45300', $resultado->cod_giro);
        $this->assertSame('Mediano', $resultado->tipo_contribuyente);

        // La dirección y ubicación homologada no deben sobreescribirse
        $this->assertSame('Calle Los Próceres #10', $resultado->direccion);
        $this->assertSame('San Salvador', $resultado->departamento);
        $this->assertSame('06', $resultado->cod_departamento);
        $this->assertSame('San Salvador Centro', $resultado->municipio);
        $this->assertSame('0614', $resultado->cod_municipio);
    }

    public function test_rellena_campos_vacios_y_vincula_shopify_customer_id(): void
    {
        $cliente = new Cliente([
            'nombre' => '',
            'apellido' => '',
            'nombre_empresa' => null,
            'tipo' => null,
            'correo' => '',
            'telefono' => '',
            'direccion' => '',
            'shopify_customer_id' => null,
        ]);

        $shopifyData = [
            'nombre' => 'Carlos',
            'apellido' => 'Mendez',
            'nombre_empresa' => 'Distribuidora CM',
            'tipo' => 'Empresa',
            'telefono' => '7123-4567',
            'correo' => 'carlos@mendez.com',
            'direccion' => 'Boulevard Constitución',
            'departamento' => 'San Salvador',
            'cod_departamento' => '06',
            'municipio' => 'San Salvador',
            'cod_municipio' => '0614',
            'pais' => 'El Salvador',
            'cod_pais' => 'SV',
            'shopify_customer_id' => 555666777,
        ];

        $resultado = $this->methodActualizarCliente->invoke($this->controller, $cliente, $shopifyData);

        // Deben completarse porque estaban vacíos
        $this->assertSame(555666777, $resultado->shopify_customer_id);
        $this->assertSame('Carlos', $resultado->nombre);
        $this->assertSame('Mendez', $resultado->apellido);
        $this->assertSame('Distribuidora CM', $resultado->nombre_empresa);
        $this->assertSame('Empresa', $resultado->tipo);
        $this->assertSame('7123-4567', $resultado->telefono);
        $this->assertSame('carlos@mendez.com', $resultado->correo);
        $this->assertSame('Boulevard Constitución', $resultado->direccion);
        $this->assertSame('San Salvador', $resultado->departamento);
        $this->assertSame('06', $resultado->cod_departamento);
    }
}
