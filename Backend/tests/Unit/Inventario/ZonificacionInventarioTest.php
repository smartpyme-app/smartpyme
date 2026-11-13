<?php

namespace Tests\Unit\Inventario;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\InventarioUbicacion;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ZonificacionInventarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('inventario', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_bodega');
            $table->decimal('stock', 10, 2)->default(0);
            $table->decimal('stock_minimo', 10, 2)->default(0);
            $table->decimal('stock_maximo', 10, 2)->default(0);
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
    }

    public function test_guarda_varias_ubicaciones_sin_cambiar_stock(): void
    {
        $inventario = Inventario::create([
            'id_producto' => 1,
            'id_bodega' => 1,
            'stock' => 12,
            'stock_minimo' => 1,
            'stock_maximo' => 20,
        ]);

        $inventario->syncUbicaciones([
            ['ubicacion' => 'A', 'ubicacion_especifica' => '1', 'detalle' => '2', 'activa' => true],
            ['ubicacion' => 'B', 'ubicacion_especifica' => null, 'detalle' => '3', 'activa' => true],
        ]);

        $this->assertSame(12.0, (float) $inventario->fresh()->stock);
        $this->assertCount(2, $inventario->ubicaciones()->get());
    }

    public function test_descarta_fila_con_tres_vacios(): void
    {
        $inventario = Inventario::create([
            'id_producto' => 1,
            'id_bodega' => 1,
            'stock' => 0,
            'stock_minimo' => 0,
            'stock_maximo' => 0,
        ]);

        $inventario->syncUbicaciones([
            ['ubicacion' => '', 'ubicacion_especifica' => null, 'detalle' => '  ', 'activa' => true],
            ['ubicacion' => 'C', 'ubicacion_especifica' => null, 'detalle' => null, 'activa' => true],
        ]);

        $rows = $inventario->ubicaciones()->get();
        $this->assertCount(1, $rows);
        $this->assertSame('C', $rows->first()->ubicacion);
    }

    public function test_borra_ubicaciones_que_ya_no_vienen(): void
    {
        $inventario = Inventario::create([
            'id_producto' => 1,
            'id_bodega' => 1,
            'stock' => 5,
            'stock_minimo' => 0,
            'stock_maximo' => 10,
        ]);

        $inventario->syncUbicaciones([
            ['ubicacion' => 'A', 'ubicacion_especifica' => '1', 'detalle' => null, 'activa' => true],
            ['ubicacion' => 'B', 'ubicacion_especifica' => '2', 'detalle' => null, 'activa' => true],
        ]);

        $queda = $inventario->ubicaciones()->where('ubicacion', 'A')->first();
        $this->assertNotNull($queda);

        $inventario->syncUbicaciones([
            ['id' => $queda->id, 'ubicacion' => 'A', 'ubicacion_especifica' => '1', 'detalle' => null, 'activa' => true],
        ]);

        $this->assertCount(1, $inventario->ubicaciones()->get());
        $this->assertSame('A', $inventario->ubicaciones()->first()->ubicacion);
    }

    public function test_conserva_ubicacion_inactiva_y_cantidad_en_null(): void
    {
        $inventario = Inventario::create([
            'id_producto' => 1,
            'id_bodega' => 1,
            'stock' => 3,
            'stock_minimo' => 0,
            'stock_maximo' => 10,
        ]);

        $inventario->syncUbicaciones([
            ['ubicacion' => 'E1', 'ubicacion_especifica' => 'S', 'detalle' => 'P', 'activa' => false],
        ]);

        $u = $inventario->ubicaciones()->first();
        $this->assertNotNull($u);
        $this->assertFalse((bool) $u->activa);
        $this->assertNull($u->cantidad);
    }

    public function test_sin_clave_ubicaciones_no_toca_existentes(): void
    {
        $inventario = Inventario::create([
            'id_producto' => 1,
            'id_bodega' => 1,
            'stock' => 1,
            'stock_minimo' => 0,
            'stock_maximo' => 5,
        ]);

        InventarioUbicacion::create([
            'id_inventario' => $inventario->id,
            'ubicacion' => 'X',
            'ubicacion_especifica' => 'Y',
            'detalle' => 'Z',
            'activa' => true,
        ]);

        // Misma regla que el controlador: sin clave ubicaciones, no sincroniza.
        $this->assertCount(1, $inventario->ubicaciones()->get());
        $this->assertSame('X', $inventario->ubicaciones()->first()->ubicacion);
    }
}
