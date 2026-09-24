<?php

namespace Tests\Unit\Services;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Imagen;
use App\Models\Inventario\Producto;
use App\Services\ShopifyApiClient;
use App\Services\ShopifyImageService;
use App\Http\Controllers\Api\Webhook\ShopifyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopifyImageSyncTest extends TestCase
{
    private ShopifyImageService $imageService;
    private string $tempImgPath;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('empresas', function ($table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('shopify_store_url')->nullable();
            $table->string('shopify_consumer_secret')->nullable();
            $table->string('shopify_client_secret')->nullable();
            $table->string('shopify_access_token')->nullable();
            $table->timestamps();
        });

        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->unsignedBigInteger('shopify_variant_id')->nullable();
            $table->string('nombre')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('productos_imagenes', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('shopify_image_id')->nullable();
            $table->string('img')->nullable();
            $table->text('src')->nullable();
            $table->string('hash')->nullable();
            $table->timestamps();
        });

        $this->imageService = new ShopifyImageService();

        // Create temporary test image in public_path('img/productos')
        $dir = public_path('img/productos');
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->tempImgPath = $dir . '/test_unit_img.jpg';
        file_put_contents($this->tempImgPath, 'fake_image_binary_content_12345');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempImgPath)) {
            @unlink($this->tempImgPath);
        }
        parent::tearDown();
    }

    public function test_subir_imagen_a_shopify_con_attachment_base64()
    {
        $empresa = Empresa::create([
            'nombre' => 'Test Empresa',
            'shopify_store_url' => 'https://test.myshopify.com',
            'shopify_access_token' => 'shpat_123456',
        ]);

        $producto = Producto::create([
            'id_empresa' => $empresa->id,
            'shopify_product_id' => 999888,
            'shopify_variant_id' => 777666,
            'nombre' => 'Producto Prueba',
        ]);

        $imagen = Imagen::create([
            'id_producto' => $producto->id,
            'img' => '/productos/test_unit_img.jpg',
        ]);

        $clientMock = $this->createMock(ShopifyApiClient::class);
        $clientMock->expects($this->once())
            ->method('post')
            ->with(
                'products/999888/images.json',
                $this->callback(function ($payload) {
                    $attachment = $payload['image']['attachment'] ?? null;
                    $expected = base64_encode('fake_image_binary_content_12345');
                    $variantIds = $payload['image']['variant_ids'] ?? [];
                    return $attachment === $expected
                        && $variantIds === [777666]
                        && ($payload['image']['filename'] ?? '') === 'test_unit_img.jpg';
                })
            )
            ->willReturn([
                'status' => 'success',
                'body' => [
                    'image' => [
                        'id' => 11223344,
                        'src' => 'https://cdn.shopify.com/s/files/1/test_unit_img.jpg',
                    ]
                ]
            ]);

        $res = $this->imageService->subirImagenAShopify($imagen, $clientMock);

        $this->assertTrue($res);
        $imagen->refresh();
        $this->assertEquals(11223344, $imagen->shopify_image_id);
        $this->assertEquals('https://cdn.shopify.com/s/files/1/test_unit_img.jpg', $imagen->src);
        $this->assertNotEmpty($imagen->hash);

        // Verify cache lock was set for echo prevention
        $this->assertTrue(Cache::has("shopify_syncing_img_{$producto->id}"));
    }

    public function test_subir_imagen_reemplaza_y_elimina_imagen_anterior_en_shopify()
    {
        $empresa = Empresa::create([
            'nombre' => 'Test Empresa',
            'shopify_store_url' => 'https://test.myshopify.com',
            'shopify_access_token' => 'shpat_123456',
        ]);

        $producto = Producto::create([
            'id_empresa' => $empresa->id,
            'shopify_product_id' => 999888,
            'nombre' => 'Producto Prueba',
        ]);

        $imagen = Imagen::create([
            'id_producto' => $producto->id,
            'shopify_image_id' => 55555,
            'img' => '/productos/test_unit_img.jpg',
        ]);

        $clientMock = $this->createMock(ShopifyApiClient::class);
        $clientMock->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 'success',
                'body' => [
                    'image' => [
                        'id' => 66666,
                        'src' => 'https://cdn.shopify.com/s/files/1/new.jpg',
                    ]
                ]
            ]);

        // Expect delete for previous shopify_image_id 55555
        $clientMock->expects($this->once())
            ->method('delete')
            ->with('products/999888/images/55555.json')
            ->willReturn(['status' => 'success']);

        $res = $this->imageService->subirImagenAShopify($imagen, $clientMock);

        $this->assertTrue($res);
        $imagen->refresh();
        $this->assertEquals(66666, $imagen->shopify_image_id);
    }

    public function test_eliminar_imagen_de_shopify()
    {
        $empresa = Empresa::create([
            'nombre' => 'Test Empresa',
            'shopify_store_url' => 'https://test.myshopify.com',
            'shopify_access_token' => 'shpat_123456',
        ]);

        $producto = Producto::create([
            'id_empresa' => $empresa->id,
            'shopify_product_id' => 999888,
        ]);

        $imagen = Imagen::create([
            'id_producto' => $producto->id,
            'shopify_image_id' => 888999,
            'img' => '/productos/test_unit_img.jpg',
        ]);

        $clientMock = $this->createMock(ShopifyApiClient::class);
        $clientMock->expects($this->once())
            ->method('delete')
            ->with('products/999888/images/888999.json')
            ->willReturn(['status' => 'success']);

        $res = $this->imageService->eliminarImagenDeShopify($imagen, $clientMock);

        $this->assertTrue($res);
        $this->assertTrue(Cache::has("shopify_syncing_img_{$producto->id}"));
    }

    public function test_procesar_imagenes_omite_webhook_cuando_cache_lock_activo()
    {
        $productoId = 12345;
        Cache::put("shopify_syncing_img_{$productoId}", true, 60);

        $imageServiceMock = $this->createMock(ShopifyImageService::class);
        // Debe ser 0 veces porque el bloqueo anti-eco está activo
        $imageServiceMock->expects($this->never())->method('sincronizarImagenes');

        $controller = new ShopifyController(
            $this->createMock(\App\Services\ShopifyTransformer::class),
            $this->createMock(\App\Services\ShopifySyncCache::class),
            $this->createMock(\App\Services\ShippingService::class),
            $this->createMock(\App\Services\ImpuestosService::class),
            $imageServiceMock
        );

        $request = new Request([
            'images' => [
                ['id' => 999, 'src' => 'https://example.com/img.jpg']
            ]
        ]);

        $controller->procesarImagenes($request, $productoId);
    }
}
