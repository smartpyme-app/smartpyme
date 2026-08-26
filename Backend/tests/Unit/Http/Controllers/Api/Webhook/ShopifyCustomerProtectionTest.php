<?php

namespace Tests\Unit\Http\Controllers\Api\Webhook;

use App\Models\Ventas\Clientes\Cliente;
use App\Services\Shopify\ShopifyClienteService;
use Tests\TestCase;

class ShopifyCustomerProtectionTest extends TestCase
{
    private ShopifyClienteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShopifyClienteService();
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

        $shopifyData = [
            'nombre' => 'Juan Carlos',
            'apellido' => 'Perez Gomez',
            'nombre_empresa' => null,
            'tipo' => 'Persona',
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

        $resultado = $this->service->actualizarClienteExistenteDesdeShopify($cliente, $shopifyData);

        $this->assertSame('Juan Carlos', $resultado->nombre);
        $this->assertSame('Perez Gomez', $resultado->apellido);
        $this->assertSame('7777-8888', $resultado->telefono);
        $this->assertSame('Colonia Nueva #456', $resultado->direccion);

        $this->assertSame('Mi Empresa S.A. de C.V.', $resultado->nombre_empresa);
        $this->assertSame('Empresa', $resultado->tipo);
        $this->assertSame('123456-7', $resultado->ncr);
        $this->assertSame('0614-010190-001-1', $resultado->nit);
        $this->assertSame('01234567-8', $resultado->dui);
        $this->assertSame('Venta de repuestos y accesorios', $resultado->giro);
        $this->assertSame('45300', $resultado->cod_giro);
        $this->assertSame('Mediano', $resultado->tipo_contribuyente);

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

        $resultado = $this->service->actualizarClienteExistenteDesdeShopify($cliente, $shopifyData);

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

    public function test_actualiza_ubicacion_cuando_codigos_estan_vacios_aunque_labels_tengan_texto(): void
    {
        $cliente = new Cliente([
            'nombre' => 'José',
            'apellido' => 'España',
            'correo' => 'jose@gmail.com',
            'telefono' => '+50377310932',
            'direccion' => 'Calle Vieja',
            'departamento' => 'Santa Ana',
            'cod_departamento' => '02',
            'municipio' => 'SAN SALVAD',
            'cod_municipio' => 'SAN SALVAD',
            'distrito' => null,
            'cod_distrito' => null,
            'shopify_customer_id' => 27540943208818,
        ]);

        $shopifyData = [
            'nombre' => 'José',
            'apellido' => 'España',
            'correo' => 'jose@gmail.com',
            'telefono' => '+50377310932',
            'direccion' => '2a Calle Poniente',
            'departamento' => 'Santa Ana',
            'cod_departamento' => '02',
            'municipio' => 'SANTA ANA OESTE',
            'cod_municipio' => '17',
            'distrito' => 'CHALCHUAPA',
            'cod_distrito' => '03',
            'pais' => 'El Salvador',
            'cod_pais' => 'SV',
            'shopify_customer_id' => 27540943208818,
        ];

        $resultado = $this->service->actualizarClienteExistenteDesdeShopify($cliente, $shopifyData);

        $this->assertSame('CHALCHUAPA', $resultado->distrito);
        $this->assertSame('03', $resultado->cod_distrito);
        $this->assertSame('SANTA ANA OESTE', $resultado->municipio);
        $this->assertSame('17', $resultado->cod_municipio);
        $this->assertSame('Santa Ana', $resultado->departamento);
        $this->assertSame('02', $resultado->cod_departamento);
    }

    public function test_consumidor_final_no_es_modificado_por_actualizacion_shopify(): void
    {
        $consumidorFinal = new Cliente([
            'nombre' => 'Consumidor Final',
            'apellido' => '',
            'correo' => null,
            'telefono' => null,
            'direccion' => null,
            'tipo' => 'Persona',
            'id_empresa' => 553,
        ]);

        $shopifyData = [
            'nombre' => 'Consumidor Final',
            'apellido' => 'Modificado',
            'correo' => 'test@random.com',
            'telefono' => '',
            'direccion' => 'Calle 123',
            'shopify_customer_id' => 999111222,
        ];

        $resultado = $this->service->actualizarClienteExistenteDesdeShopify($consumidorFinal, $shopifyData);

        $this->assertSame('Consumidor Final', $resultado->nombre);
        $this->assertSame('', $resultado->apellido);
        $this->assertNull($resultado->telefono);
        $this->assertNull($resultado->correo);
        $this->assertNull($resultado->direccion);
        $this->assertNull($resultado->shopify_customer_id);
    }

    public function test_telefono_vacio_no_sobreescribe_telefono_existente(): void
    {
        $cliente = new Cliente([
            'nombre' => 'Carlos',
            'apellido' => 'Perez',
            'correo' => 'carlos@perez.com',
            'telefono' => '+50378889999',
            'id_empresa' => 553,
        ]);

        $shopifyData = [
            'nombre' => 'Carlos',
            'apellido' => 'Perez',
            'correo' => 'carlos@perez.com',
            'telefono' => null,
        ];

        $resultado = $this->service->actualizarClienteExistenteDesdeShopify($cliente, $shopifyData);

        $this->assertSame('+50378889999', $resultado->telefono);
    }
}
