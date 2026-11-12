<?php

namespace Tests\Unit\Http\Controllers\Api\Compras\Proveedores;

use App\Http\Controllers\Api\Compras\Proveedores\ProveedoresController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProveedoresListMinimalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.prefix' => '']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->string('nombre_empresa')->nullable();
            $table->string('nombre_comercial')->nullable();
            $table->string('tipo')->nullable();
            $table->string('nit')->nullable();
            $table->string('ncr')->nullable();
            $table->string('dui')->nullable();
            $table->string('tipo_contribuyente')->nullable();
            $table->text('etiquetas')->nullable();
            $table->boolean('enable')->default(true);
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->timestamps();
        });

        DB::table('proveedores')->insert([
            [
                'nombre' => 'Ana',
                'apellido' => 'Ruiz',
                'nombre_empresa' => null,
                'nombre_comercial' => 'Ana Comercial',
                'tipo' => 'Persona',
                'nit' => '0614-1',
                'ncr' => null,
                'tipo_contribuyente' => 'Grande',
                'etiquetas' => '["vip"]',
                'enable' => 1,
                'id_empresa' => 1,
            ],
            [
                'nombre' => 'Luis',
                'apellido' => null,
                'nombre_empresa' => null,
                'nombre_comercial' => null,
                'tipo' => 'Persona',
                'nit' => '0000-0',
                'ncr' => '123456',
                'tipo_contribuyente' => null,
                'etiquetas' => null,
                'enable' => 1,
                'id_empresa' => 1,
            ],
            [
                'nombre' => 'Beta',
                'apellido' => 'Off',
                'nombre_empresa' => null,
                'nombre_comercial' => null,
                'tipo' => 'Persona',
                'nit' => '9999-9',
                'ncr' => null,
                'tipo_contribuyente' => null,
                'etiquetas' => null,
                'enable' => 0,
                'id_empresa' => 1,
            ],
        ]);
    }

    public function test_sin_minimal_devuelve_el_modelo_completo(): void
    {
        $data = $this->listar([]);

        $this->assertCount(2, $data);
        $this->assertSame('0614-1', $data[0]['nit']);
        $this->assertSame('Ana Ruiz', $data[0]['nombre_completo']);
        $this->assertSame(['vip'], $data[0]['etiquetas']);
    }

    public function test_minimal_devuelve_solo_campos_de_selector(): void
    {
        $data = $this->listar(['minimal' => 1]);

        $this->assertCount(2, $data);
        $this->assertSame(
            ['id', 'tipo', 'nombre', 'nombre_empresa', 'nombre_comercial', 'nombre_completo'],
            array_keys($data[0])
        );
        $this->assertSame('Ana Ruiz', $data[0]['nombre_completo']);
        $this->assertSame('Luis ', $data[1]['nombre_completo']);
        $this->assertArrayNotHasKey('nit', $data[0]);
        $this->assertArrayNotHasKey('etiquetas', $data[0]);
    }

    public function test_buscar_emisor_por_nit_no_trae_etiquetas(): void
    {
        $data = $this->buscar(['nit' => '0614-1']);

        $this->assertSame('Grande', $data['tipo_contribuyente']);
        $this->assertSame('Ana Ruiz', $data['nombre_completo']);
        $this->assertArrayNotHasKey('etiquetas', $data);
    }

    public function test_buscar_emisor_sigue_con_ncr_si_el_nit_no_esta(): void
    {
        $data = $this->buscar(['nit' => 'no-existe', 'ncr' => '123456']);

        $this->assertSame('Luis ', $data['nombre_completo']);
    }

    public function test_buscar_emisor_encuentra_inactivo_y_desconocido_es_null(): void
    {
        $this->assertSame('Beta', $this->buscar(['nit' => '9999-9'])['nombre']);
        $this->assertSame([], $this->buscar(['nit' => 'x']));
    }

    private function buscar(array $query): ?array
    {
        $response = (new ProveedoresController)->buscarEmisor(
            Request::create('/proveedores/buscar-emisor', 'GET', $query)
        );

        return $response->getData(true);
    }

    private function listar(array $query): array
    {
        $response = (new ProveedoresController)->list(Request::create('/proveedores/list', 'GET', $query));

        return $response->getData(true);
    }
}
