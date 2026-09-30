<?php

namespace Tests\Unit\Inventario;

use App\Support\Inventario\ActualizacionMasivaProductos;
use PHPUnit\Framework\TestCase;

class ActualizacionMasivaProductosTest extends TestCase
{
    private function producto(): array
    {
        return [
            'id' => 10,
            'tipo' => 'Producto',
            'codigo' => 'A-1',
            'costo' => 5,
            'costo_promedio' => 4,
            'precio' => 10,
            'precio_sin_iva' => 10,
            'precio_con_iva' => 11.3,
            'mostrar_en_restaurante' => true,
            'genera_comanda' => false,
            'stock' => 8,
        ];
    }

    private function inventarios(): array
    {
        return [
            3 => ['stock' => 8, 'stock_minimo' => 1, 'stock_maximo' => 20],
            7 => ['stock' => 2, 'stock_minimo' => 0, 'stock_maximo' => 5],
        ];
    }

    public function test_actualiza_campos_generales_y_recalcula_precios(): void
    {
        $r = (new ActualizacionMasivaProductos())->evaluarFila(
            [
                'id_creacion' => 10,
                'codigo' => 'B-2',
                'costo' => 6,
                'precio_sin_iva' => 100,
                'entra_a_menu' => 'No',
                'genera_comanda' => 'Si',
            ],
            $this->producto(),
            $this->inventarios(),
            [3, 7],
            [10 => 'A-1'],
            13
        );

        $this->assertTrue($r['ok']);
        $this->assertSame('B-2', $r['producto']['codigo']);
        $this->assertSame(6.0, $r['producto']['costo']);
        $this->assertSame(6.0, $r['producto']['costo_promedio']);
        $this->assertSame(100.0, $r['producto']['precio']);
        $this->assertSame(100.0, $r['producto']['precio_sin_iva']);
        $this->assertSame(113.0, $r['producto']['precio_con_iva']);
        $this->assertFalse($r['producto']['mostrar_en_restaurante']);
        $this->assertTrue($r['producto']['genera_comanda']);
        $this->assertArrayNotHasKey('stock', $r['producto']);
        $this->assertTrue($r['registrar_kardex']);
    }

    public function test_celda_vacia_no_cambia_el_dato(): void
    {
        $r = (new ActualizacionMasivaProductos())->evaluarFila(
            [
                'id_creacion' => 10,
                'codigo' => '',
                'costo' => null,
                'precio_sin_iva' => '',
                'entra_a_menu' => '',
                'genera_comanda' => null,
                'stock_minimo_3_centro' => '',
                'stock_maximo_3_centro' => null,
            ],
            $this->producto(),
            $this->inventarios(),
            [3, 7],
            [10 => 'A-1'],
            13
        );

        $this->assertTrue($r['ok']);
        $this->assertSame([], $r['producto']);
        $this->assertSame([], $r['inventarios']);
        $this->assertFalse($r['registrar_kardex']);
    }

    public function test_stock_minimo_de_una_bodega_no_toca_la_otra_ni_la_existencia(): void
    {
        $r = (new ActualizacionMasivaProductos())->evaluarFila(
            [
                'id_creacion' => 10,
                'stock_minimo_3_centro_sucursal' => 4,
            ],
            $this->producto(),
            $this->inventarios(),
            [3, 7],
            [10 => 'A-1'],
            13
        );

        $this->assertTrue($r['ok']);
        $this->assertSame([3 => ['stock_minimo' => 4.0]], $r['inventarios']);
        $this->assertArrayNotHasKey('stock', $r['inventarios'][3]);
        $this->assertArrayNotHasKey(7, $r['inventarios']);
    }

    public function test_id_inexistente_no_crea_producto(): void
    {
        $r = (new ActualizacionMasivaProductos())->evaluarFila(
            ['id_creacion' => 99, 'codigo' => 'NUEVO'],
            null,
            [],
            [3],
            [],
            13
        );

        $this->assertFalse($r['ok']);
        $this->assertSame('El ID de creación no existe.', $r['error']);
        $this->assertArrayNotHasKey('producto', $r);
    }

    public function test_bodega_sin_inventario_rechaza_la_fila(): void
    {
        $r = (new ActualizacionMasivaProductos())->evaluarFila(
            ['id_creacion' => 10, 'stock_maximo_7_norte' => 9],
            $this->producto(),
            [3 => ['stock' => 8, 'stock_minimo' => 1, 'stock_maximo' => 20]],
            [3, 7],
            [10 => 'A-1'],
            13
        );

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no tiene inventario', $r['error']);
    }

    public function test_bodega_que_no_es_de_la_empresa_rechaza_la_fila(): void
    {
        $r = (new ActualizacionMasivaProductos())->evaluarFila(
            ['id_creacion' => 10, 'stock_minimo_99_ajena' => 1],
            $this->producto(),
            $this->inventarios(),
            [3, 7],
            [10 => 'A-1'],
            13
        );

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no es una bodega', $r['error']);
    }

    public function test_codigo_duplicado_rechaza_la_fila(): void
    {
        $r = (new ActualizacionMasivaProductos())->evaluarFila(
            ['id_creacion' => 10, 'codigo' => 'OTRO'],
            $this->producto(),
            $this->inventarios(),
            [3, 7],
            [10 => 'A-1', 22 => 'OTRO'],
            13
        );

        $this->assertFalse($r['ok']);
        $this->assertSame('El código ya pertenece a otro producto.', $r['error']);
    }

    public function test_cero_en_stock_minimo_si_actualiza(): void
    {
        $r = (new ActualizacionMasivaProductos())->evaluarFila(
            ['id_creacion' => 10, 'stock_minimo_3_centro' => 0],
            $this->producto(),
            $this->inventarios(),
            [3, 7],
            [10 => 'A-1'],
            13
        );

        $this->assertTrue($r['ok']);
        $this->assertSame(0.0, $r['inventarios'][3]['stock_minimo']);
    }
}
