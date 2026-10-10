<?php

namespace Tests\Unit\Services;

use App\Services\ProductImageStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageStorageTest extends TestCase
{
    public function test_url_builds_public_cdn_path(): void
    {
        config([
            'product_images.url' => 'https://sp-imagenes-productos.s3.us-east-2.amazonaws.com',
        ]);

        $storage = new ProductImageStorage();
        $url = $storage->url('/productos/abc123.jpg');

        $this->assertSame(
            'https://sp-imagenes-productos.s3.us-east-2.amazonaws.com/productos/abc123.jpg',
            $url
        );
    }

    public function test_default_image_url_returns_null_for_cdn(): void
    {
        config(['product_images.url' => 'https://cdn.example.com']);
        $storage = new ProductImageStorage();
        $this->assertNull($storage->url('productos/default.jpg'));
        $this->assertTrue($storage->isDefaultImage('/productos/default.jpg'));
    }

    public function test_local_path_avoids_duplicate_productos_segment(): void
    {
        config(['product_images.local_root' => '/home/smartpyme/public_html/api/img/productos']);

        $storage = new ProductImageStorage();
        $path = $storage->localAbsolutePath('/productos/abc123.jpg');

        $this->assertSame(
            '/home/smartpyme/public_html/api/img/productos/abc123.jpg',
            str_replace('\\', '/', $path)
        );
    }

    public function test_put_and_get_on_fake_disk(): void
    {
        Storage::fake('s3_productos');
        config(['product_images.disk' => 's3_productos']);

        $storage = new ProductImageStorage();
        $ok = $storage->putRawForImg('/productos/testhash.jpg', 'binary-jpg');
        $this->assertTrue($ok);
        $this->assertSame('binary-jpg', $storage->get('/productos/testhash.jpg'));
    }
}
