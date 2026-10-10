<?php

namespace Tests\Unit\Services;

use App\Models\Inventario\Imagen;
use App\Models\Inventario\Producto;
use App\Services\ProductImageStorage;
use App\Services\ShopifyImageService;
use App\Services\WooCommerceProductImageSyncService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WooCommerceProductImageSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'product_images.disk' => 's3_productos',
        ]);

        Storage::fake('s3_productos');

        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->timestamps();
        });

        Schema::create('productos_imagenes', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->string('img')->nullable();
            $table->text('src')->nullable();
            $table->string('hash')->nullable();
            $table->unsignedBigInteger('shopify_image_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_sync_skips_when_same_src_and_image_exists(): void
    {
        $producto = Producto::withoutEvents(fn () => Producto::create(['id_empresa' => 1, 'nombre' => 'Woo product']));

        Imagen::withoutEvents(fn () => Imagen::create([
            'id_producto' => $producto->id,
            'img' => '/productos/existing.jpg',
            'src' => 'https://woo.example/image.jpg',
        ]));

        $storage = $this->createMock(ProductImageStorage::class);
        $storage->method('exists')->with('/productos/existing.jpg')->willReturn(true);
        $storage->expects($this->never())->method('storeJpgFromBinary');

        $shopifyImages = $this->createMock(ShopifyImageService::class);
        $shopifyImages->method('validarUrl')
            ->willReturnCallback(fn ($url) => is_string($url) && str_starts_with($url, 'https://') ? $url : null);

        $service = new WooCommerceProductImageSyncService($storage, $shopifyImages);

        $service->syncFromPayload($producto->id, [
            ['src' => 'https://woo.example/image.jpg'],
        ]);

        $this->assertDatabaseCount('productos_imagenes', 1);
    }
}
