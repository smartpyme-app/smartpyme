<?php

namespace Tests\Unit\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Admin\Empresa;
use App\Services\ImpuestosService;
use App\Services\ShippingService;
use App\Services\ShopifyImageService;
use App\Services\ShopifyLocationService;
use App\Services\ShopifySyncCache;
use App\Services\ShopifyTransformer;
use Illuminate\Http\Request;
use ReflectionClass;
use Tests\TestCase;

class ShopifyLocationWebhookTest extends TestCase
{
    private ShopifyController $controller;
    private $locationServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $impuestosService = $this->createMock(ImpuestosService::class);
        $transformer = new ShopifyTransformer($impuestosService);
        $cache = $this->createMock(ShopifySyncCache::class);
        $shippingService = $this->createMock(ShippingService::class);
        $imageService = $this->createMock(ShopifyImageService::class);

        $this->controller = new ShopifyController(
            $transformer,
            $cache,
            $shippingService,
            $impuestosService,
            $imageService
        );

        $this->locationServiceMock = $this->createMock(ShopifyLocationService::class);
        $this->app->instance(ShopifyLocationService::class, $this->locationServiceMock);
    }

    public function test_procesar_ubicacion_creada_exitoso(): void
    {
        $empresa = new Empresa();
        $empresa->id = 123;

        $request = new Request([
            'id' => 999111,
            'name' => 'Sucursal San Salvador',
            'active' => true,
        ]);

        $this->locationServiceMock
            ->expects($this->once())
            ->method('crearSucursalDesdeShopifyPayload')
            ->willReturn([
                'success' => true,
                'mensaje' => 'Sucursal creada exitosamente',
            ]);

        $ref = new ReflectionClass($this->controller);
        $method = $ref->getMethod('procesarUbicacionCreadaShopify');
        $method->setAccessible(true);

        $response = $method->invoke($this->controller, $request, $empresa);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('Sucursal creada exitosamente', $data['mensaje']);
    }

    public function test_procesar_ubicacion_actualizada_exitoso(): void
    {
        $empresa = new Empresa();
        $empresa->id = 123;

        $request = new Request([
            'id' => 999111,
            'name' => 'Sucursal San Salvador Renombrada',
            'active' => true,
        ]);

        $this->locationServiceMock
            ->expects($this->once())
            ->method('actualizarSucursalDesdeShopifyPayload')
            ->with($request->all(), $empresa, 'locations/update')
            ->willReturn([
                'success' => true,
                'mensaje' => 'Sucursal actualizada exitosamente',
            ]);

        $ref = new ReflectionClass($this->controller);
        $method = $ref->getMethod('procesarUbicacionActualizadaShopify');
        $method->setAccessible(true);

        $response = $method->invoke($this->controller, $request, $empresa, 'locations/update');

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
    }

    public function test_procesar_ubicacion_eliminada_exitoso(): void
    {
        $empresa = new Empresa();
        $empresa->id = 123;

        $request = new Request([
            'id' => 999111,
        ]);

        $this->locationServiceMock
            ->expects($this->once())
            ->method('eliminarSucursalDesdeShopify')
            ->with(999111, $empresa)
            ->willReturn([
                'success' => true,
                'mensaje' => 'Sucursal eliminada exitosamente',
                'accion' => 'eliminada',
            ]);

        $ref = new ReflectionClass($this->controller);
        $method = $ref->getMethod('procesarUbicacionEliminadaShopify');
        $method->setAccessible(true);

        $response = $method->invoke($this->controller, $request, $empresa);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame('eliminada', $data['accion']);
    }

    public function test_procesar_ubicacion_error_retorna_400(): void
    {
        $empresa = new Empresa();
        $empresa->id = 123;

        $request = new Request([]);

        $this->locationServiceMock
            ->expects($this->once())
            ->method('crearSucursalDesdeShopifyPayload')
            ->willReturn([
                'success' => false,
                'mensaje' => 'Payload no contiene id de ubicación.',
            ]);

        $ref = new ReflectionClass($this->controller);
        $method = $ref->getMethod('procesarUbicacionCreadaShopify');
        $method->setAccessible(true);

        $response = $method->invoke($this->controller, $request, $empresa);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
    }
}
