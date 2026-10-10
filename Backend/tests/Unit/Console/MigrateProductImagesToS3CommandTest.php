<?php

namespace Tests\Unit\Console;

use App\Models\Inventario\Imagen;
use App\Models\Inventario\Producto;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateProductImagesToS3CommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'product_images.disk' => 's3_productos',
            'filesystems.disks.s3_productos.bucket' => 'sp-imagenes-productos',
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
            $table->timestamps();
        });
    }

    public function test_dry_run_does_not_upload_or_delete_local(): void
    {
        $dir = public_path('img/productos');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $localFile = $dir . '/dryrun.jpg';
        file_put_contents($localFile, 'local-bytes');

        $producto = Producto::withoutEvents(fn () => Producto::create(['id_empresa' => 1, 'nombre' => 'P']));
        Imagen::withoutEvents(fn () => Imagen::create([
            'id_producto' => $producto->id,
            'img' => '/productos/dryrun.jpg',
        ]));

        $this->artisan('imagenes:migrate-to-s3', ['--dry-run' => true])
            ->assertExitCode(0);

        Storage::disk('s3_productos')->assertMissing('productos/dryrun.jpg');
        $this->assertFileExists($localFile);

        @unlink($localFile);
    }

    public function test_upload_deletes_local_file_on_success(): void
    {
        $dir = public_path('img/productos');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $localFile = $dir . '/uploadme.jpg';
        file_put_contents($localFile, 'upload-bytes');

        $producto = Producto::withoutEvents(fn () => Producto::create(['id_empresa' => 1, 'nombre' => 'P']));
        Imagen::withoutEvents(fn () => Imagen::create([
            'id_producto' => $producto->id,
            'img' => '/productos/uploadme.jpg',
        ]));

        $this->artisan('imagenes:migrate-to-s3')
            ->assertExitCode(0);

        Storage::disk('s3_productos')->assertExists('productos/uploadme.jpg');
        $this->assertFileDoesNotExist($localFile);
    }
}
