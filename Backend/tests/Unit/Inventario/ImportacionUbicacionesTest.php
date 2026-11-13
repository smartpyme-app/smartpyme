<?php

namespace Tests\Unit\Inventario;

use App\Models\Inventario\Inventario;
use App\Support\Inventario\ImportacionUbicaciones;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportacionUbicacionesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('tipo')->nullable();
            $table->softDeletes();
        });

        Schema::create('sucursal_bodegas', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->string('nombre');
            $table->boolean('activo')->default(true);
        });

        Schema::create('inventario', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_bodega');
            $table->decimal('stock', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('inventario_ubicaciones', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_inventario');
            $table->string('ubicacion', 100)->nullable();
            $table->string('ubicacion_especifica', 100)->nullable();
            $table->text('detalle')->nullable();
            $table->boolean('activa')->default(true);
            $table->decimal('cantidad', 10, 2)->nullable();
            $table->timestamps();
        });

        DB::table('productos')->insert([
            'id' => 1, 'id_empresa' => 5, 'codigo' => 'A-1', 'nombre' => 'Filtro', 'tipo' => 'Producto',
        ]);
        DB::table('sucursal_bodegas')->insert([
            'id' => 3, 'id_empresa' => 5, 'nombre' => 'Central', 'activo' => 1,
        ]);
        Inventario::create([
            'id_producto' => 1,
            'id_bodega' => 3,
            'stock' => 8,
        ]);
    }

    public function test_agrega_ubicacion_sin_cambiar_stock(): void
    {
        $r = (new ImportacionUbicaciones())->procesar([
            [
                'codigo' => 'A-1',
                'bodega' => 'Central',
                'ubicacion' => 'Pasillo A',
                'ubicacion_especifica' => 'Estante 3',
                'detalle' => 'Nivel 2',
                'sin_existencias' => 'No',
            ],
        ], 5);

        $this->assertSame(1, $r['agregados']);
        $this->assertSame([], $r['errores']);
        $this->assertSame(8.0, (float) Inventario::first()->stock);
        $this->assertSame('Pasillo A', Inventario::first()->ubicaciones()->first()->ubicacion);
        $this->assertNull(Inventario::first()->ubicaciones()->first()->cantidad);
    }

    public function test_actualiza_por_id_y_no_crea_otra(): void
    {
        $creada = (new ImportacionUbicaciones())->procesar([
            ['codigo' => 'A-1', 'bodega' => 'central', 'ubicacion' => 'A', 'sin_existencias' => 'No'],
        ], 5);
        $this->assertSame(1, $creada['agregados']);

        $id = Inventario::first()->ubicaciones()->first()->id;
        $r = (new ImportacionUbicaciones())->procesar([
            ['id_ubicacion' => $id, 'ubicacion' => 'B', 'sin_existencias' => 'Si'],
        ], 5);

        $this->assertSame(1, $r['actualizados']);
        $this->assertSame(0, $r['agregados']);
        $fila = Inventario::first()->ubicaciones()->first();
        $this->assertSame('B', $fila->ubicacion);
        $this->assertFalse((bool) $fila->activa);
        $this->assertSame(1, Inventario::first()->ubicaciones()->count());
    }

    public function test_agrega_por_nombre_sin_codigo(): void
    {
        $r = (new ImportacionUbicaciones())->procesar([
            [
                'nombre' => 'Filtro',
                'bodega' => 'Central',
                'ubicacion' => 'Pasillo B',
            ],
        ], 5);

        $this->assertSame(1, $r['agregados']);
        $this->assertSame([], $r['errores']);
        $this->assertSame('Pasillo B', Inventario::first()->ubicaciones()->first()->ubicacion);
    }

    public function test_encuentra_el_nombre_aunque_tenga_espacios_o_saltos(): void
    {
        DB::table('productos')->where('id', 1)->update(['nombre' => "  Filtro\n  de aceite  "]);

        $r = (new ImportacionUbicaciones())->procesar([
            ['nombre' => 'Filtro de aceite', 'bodega' => 'Central', 'ubicacion' => 'Pasillo B'],
        ], 5);

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['agregados']);
    }

    public function test_si_el_nombre_de_bodega_se_repite_usa_la_que_tiene_inventario(): void
    {
        DB::table('sucursal_bodegas')->insert([
            'id' => 9, 'id_empresa' => 5, 'nombre' => 'CENTRAL', 'activo' => 1,
        ]);

        $r = (new ImportacionUbicaciones())->procesar([
            ['nombre' => 'Filtro', 'bodega' => 'Central', 'ubicacion' => 'Pasillo B'],
        ], 5);

        $this->assertSame([], $r['errores']);
        $this->assertSame(1, $r['agregados']);
        $this->assertSame(1, Inventario::where('id_bodega', 3)->first()->ubicaciones()->count());
        $this->assertSame(0, Inventario::where('id_bodega', 9)->count());
    }

    public function test_no_elige_si_las_dos_bodegas_tienen_inventario(): void
    {
        DB::table('sucursal_bodegas')->insert([
            'id' => 9, 'id_empresa' => 5, 'nombre' => 'CENTRAL', 'activo' => 1,
        ]);
        Inventario::create(['id_producto' => 1, 'id_bodega' => 9, 'stock' => 1]);

        $r = (new ImportacionUbicaciones())->procesar([
            ['codigo' => 'A-1', 'bodega' => 'Central', 'ubicacion' => 'X'],
        ], 5);

        $this->assertSame(0, $r['agregados']);
        $this->assertSame('Hay más de una bodega activa con ese nombre.', $r['errores'][0]['mensaje']);
    }

    public function test_no_duplica_si_la_ubicacion_ya_existe(): void
    {
        $fila = [
            'codigo' => 'A-1',
            'bodega' => 'Central',
            'ubicacion' => 'Pasillo A',
            'ubicacion_especifica' => 'Estante 3',
            'detalle' => 'Nivel 2',
        ];
        $servicio = new ImportacionUbicaciones();

        $this->assertSame(1, $servicio->procesar([$fila], 5)['agregados']);
        $this->assertSame(0, $servicio->procesar([$fila], 5)['agregados']);
        $this->assertSame(1, Inventario::first()->ubicaciones()->count());
    }

    public function test_no_agrega_si_no_hay_inventario_en_la_bodega(): void
    {
        $r = (new ImportacionUbicaciones())->procesar([
            ['codigo' => 'A-1', 'bodega' => 'Otra', 'ubicacion' => 'X'],
        ], 5);

        $this->assertSame(0, $r['agregados']);
        $this->assertSame('No hay una bodega activa con ese nombre.', $r['errores'][0]['mensaje']);
    }
}
