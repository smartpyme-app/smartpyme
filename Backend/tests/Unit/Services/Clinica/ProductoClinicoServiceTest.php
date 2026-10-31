<?php

namespace Tests\Unit\Services\Clinica;

use App\Services\Clinica\ProductoClinicoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductoClinicoServiceTest extends TestCase
{
    private ProductoClinicoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        $this->crearEsquemaMinimo();
        DB::table('empresas')->insert(['id' => 1]);
        DB::table('empresas')->insert(['id' => 2]);
        $this->servicio = new ProductoClinicoService();
    }

    public function test_buscar_solo_productos_marcados_de_la_empresa(): void
    {
        DB::table('productos')->insert([
            'id' => 10,
            'id_empresa' => 1,
            'nombre' => 'Amoxicilina',
            'codigo' => 'AMX',
        ]);
        DB::table('productos')->insert([
            'id' => 11,
            'id_empresa' => 1,
            'nombre' => 'Sin perfil',
        ]);
        DB::table('clinica_producto_perfiles')->insert([
            'id_empresa' => 1,
            'id_producto' => 10,
            'activo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resultado = $this->servicio->buscarMedicamentos(1, 'Amox');

        $this->assertCount(1, $resultado);
        $this->assertSame(10, $resultado[0]['id_producto']);
    }

    public function test_guardar_perfil_rechaza_producto_de_otra_empresa(): void
    {
        DB::table('productos')->insert(['id' => 20, 'id_empresa' => 2, 'nombre' => 'Otro']);

        $this->expectException(ValidationException::class);
        $this->servicio->guardarPerfil(1, 20, ['activo' => true]);
    }

    private function crearEsquemaMinimo(): void
    {
        Schema::create('empresas', function (Blueprint $table): void {
            $table->increments('id');
        });
        Schema::create('productos', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_empresa');
            $table->string('nombre');
            $table->string('codigo')->nullable();
            $table->string('medida')->nullable();
            $table->softDeletes();
        });
        Schema::create('clinica_producto_perfiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_producto');
            $table->string('via_habitual', 80)->nullable();
            $table->string('unidad_dosis', 80)->nullable();
            $table->boolean('controla_lote')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }
}
