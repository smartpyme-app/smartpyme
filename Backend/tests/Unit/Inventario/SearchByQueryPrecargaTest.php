<?php

namespace Tests\Unit\Inventario;

use App\Http\Controllers\Api\Inventario\ProductosController;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Composiciones\Composicion;
use App\Models\Inventario\Composiciones\Opcion;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Producto;
use App\Models\Inventario\ProductoPresentacion;
use Illuminate\Database\Eloquent\Collection;
use ReflectionMethod;
use Tests\TestCase;

class SearchByQueryPrecargaTest extends TestCase
{
    public function test_inventario_precargado_conserva_nombres_y_omite_bodega(): void
    {
        $sucursal = new Sucursal(['nombre' => 'San Salvador']);
        $bodega = new Bodega(['nombre' => 'Central']);
        $bodega->setRelation('sucursal', $sucursal);

        $inventario = new Inventario(['stock' => 8, 'id_bodega' => 3]);
        $inventario->setRelation('bodega', $bodega);
        $inventario->makeHidden(['bodega']);

        $array = $inventario->toArray();

        $this->assertSame('Central', $array['nombre_bodega']);
        $this->assertSame('San Salvador', $array['nombre_sucursal']);
        $this->assertArrayNotHasKey('bodega', $array);
    }

    public function test_composicion_y_opcion_usan_relaciones_precargadas(): void
    {
        $compuesto = new Producto(['nombre' => 'Harina', 'precio' => 12, 'costo' => 4]);
        $presentacion = new ProductoPresentacion(['nombre_comercial' => 'Libra']);

        $composicion = new Composicion(['id_presentacion' => 9]);
        $composicion->setRelation('compuesto', $compuesto);
        $composicion->setRelation('presentacion', $presentacion);
        $composicion->makeHidden(['presentacion']);

        $this->assertSame('Harina', $composicion->nombre_producto);
        $this->assertSame(12, $composicion->precio);
        $this->assertSame(4, $composicion->costo);
        $this->assertSame('Harina (Libra)', $composicion->nombre_compuesto);
        $this->assertArrayNotHasKey('presentacion', $composicion->toArray());

        $opcionProducto = new Producto(['nombre' => 'Azucar']);
        $opcion = new Opcion();
        $opcion->setRelation('producto', $opcionProducto);
        $opcion->makeHidden(['producto']);

        $opcionArray = $opcion->toArray();
        $this->assertSame('Azucar', $opcionArray['nombre_producto']);
        $this->assertArrayNotHasKey('producto', $opcionArray);
    }

    public function test_stock_de_busqueda_suma_lo_ya_cargado(): void
    {
        $metodo = new ReflectionMethod(ProductosController::class, 'stockSearchByQuery');
        $metodo->setAccessible(true);
        $controller = (new \ReflectionClass(ProductosController::class))->newInstanceWithoutConstructor();

        $servicio = new Producto(['tipo' => 'Servicio']);
        $this->assertNull($metodo->invoke($controller, $servicio, 1, null, true));

        $producto = new Producto(['tipo' => 'Producto', 'inventario_por_lotes' => true]);
        $producto->setRelation('lotes', new Collection([
            new Lote(['id_bodega' => 2, 'stock' => 4.5]),
            new Lote(['id_bodega' => 9, 'stock' => 1]),
        ]));

        $this->assertSame(4.5, $metodo->invoke($controller, $producto, 2, null, true));
        $this->assertSame(5.5, $metodo->invoke($controller, $producto, null, null, true));

        $sinLotes = new Producto(['tipo' => 'Producto', 'inventario_por_lotes' => false]);
        $sinLotes->setRelation('inventarios', new Collection([
            new Inventario(['id_bodega' => 3, 'stock' => 8]),
        ]));

        $this->assertSame(8.0, $metodo->invoke($controller, $sinLotes, 3, null, false));
        $this->assertSame(8.0, $metodo->invoke($controller, $sinLotes, null, null, false));
    }
}
