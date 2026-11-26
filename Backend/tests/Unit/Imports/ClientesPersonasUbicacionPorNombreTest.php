<?php

namespace Tests\Unit\Imports;

use App\Imports\ClientesPersonas;
use App\Models\MH\Departamento;
use App\Models\MH\Distrito;
use App\Models\MH\Municipio;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

class ClientesPersonasUbicacionPorNombreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('distritos');
        Schema::dropIfExists('municipios');
        Schema::dropIfExists('departamentos');

        Schema::create('departamentos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('cod');
            $table->string('nombre');
            $table->timestamps();
        });
        Schema::create('municipios', function (Blueprint $table) {
            $table->increments('id');
            $table->string('cod');
            $table->string('nombre');
            $table->string('cod_departamento')->nullable();
            $table->timestamps();
        });
        Schema::create('distritos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('cod');
            $table->string('nombre');
            $table->string('cod_departamento')->nullable();
            $table->string('cod_municipio')->nullable();
            $table->timestamps();
        });

        Departamento::create(['cod' => '05', 'nombre' => 'La Libertad']);
        Municipio::create(['cod' => '23', 'nombre' => 'LA LIBERTAD NORTE', 'cod_departamento' => '05']);
        Distrito::create([
            'cod' => '12',
            'nombre' => 'QUEZALTEPEQUE',
            'cod_departamento' => '05',
            'cod_municipio' => '23',
        ]);
    }

    public function test_nombre_pisa_codigo_incorrecto_del_excel(): void
    {
        $import = new ClientesPersonas();
        $prop = new ReflectionProperty(ClientesPersonas::class, 'esElSalvador');
        $prop->setAccessible(true);
        $prop->setValue($import, true);

        $row = $import->prepareForValidation([
            'nombre' => 'CLARA ELIZABETH',
            'departamento' => 'La Libertad',
            'cod_departamento' => 'Quezaltepeque',
            'municipio' => 'LA LIBERTAD NORTE',
            'cod_municipio' => 'xx',
            'distrito' => 'QUEZALTEPEQUE',
            'cod_distrito' => 'yy',
        ], 1);

        $this->assertSame('05', $row['cod_departamento']);
        $this->assertSame('23', $row['cod_municipio']);
        $this->assertSame('12', $row['cod_distrito']);
    }

    public function test_nombre_invalido_anula_codigo(): void
    {
        $import = new ClientesPersonas();
        $prop = new ReflectionProperty(ClientesPersonas::class, 'esElSalvador');
        $prop->setAccessible(true);
        $prop->setValue($import, true);

        $row = $import->prepareForValidation([
            'nombre' => 'X',
            'departamento' => 'No Existe',
            'cod_departamento' => '05',
        ], 1);

        $this->assertNull($row['cod_departamento']);
    }
}
