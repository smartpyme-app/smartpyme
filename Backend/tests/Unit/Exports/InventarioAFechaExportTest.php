<?php

namespace Tests\Unit\Exports;

use App\Exports\Inventario\InventarioAFechaExport;
use Tests\TestCase;

class InventarioAFechaExportTest extends TestCase
{
    public function test_indexar_gana_fecha_reciente_sobre_id_mas_alto_atrasado(): void
    {
        $snapshot = InventarioAFechaExport::indexarUltimoKardexPorFechaId([
            (object) ['id' => 200, 'fecha' => '2024-01-10 12:00:00', 'id_inventario' => 10, 'id_producto' => 99, 'total_cantidad' => 40],
            (object) ['id' => 100, 'fecha' => '2024-01-15 08:00:00', 'id_inventario' => 10, 'id_producto' => 99, 'total_cantidad' => 50],
        ]);

        $this->assertSame(50, $snapshot[10][99]);
    }

    public function test_indexar_en_empate_de_fecha_gana_id_mas_alto(): void
    {
        $snapshot = InventarioAFechaExport::indexarUltimoKardexPorFechaId([
            (object) ['id' => 10, 'fecha' => '2024-01-15 08:00:00', 'id_inventario' => 10, 'id_producto' => 99, 'total_cantidad' => 3],
            (object) ['id' => 11, 'fecha' => '2024-01-15 08:00:00', 'id_inventario' => 10, 'id_producto' => 99, 'total_cantidad' => 8],
        ]);

        $this->assertSame(8, $snapshot[10][99]);
    }

    public function test_map_usa_snapshot_keyed_y_cero_si_no_hay_kardex(): void
    {
        $export = new InventarioAFechaExport();
        $this->setExportState($export, [
            (object) ['id' => 10, 'nombre' => 'Principal'],
        ], [
            10 => [99 => 15],
        ]);

        $conKardex = $this->productoStub(99, [(object) ['id_bodega' => 10, 'stock' => 7]]);
        $this->assertSame(15, $export->map($conKardex)[6]);

        $sinKardex = $this->productoStub(100, [(object) ['id_bodega' => 10, 'stock' => 7]]);
        $this->assertSame('0', $export->map($sinKardex)[6]);

        $sinInventario = $this->productoStub(99, []);
        $this->assertSame(0, $export->map($sinInventario)[6]);
    }

    private function setExportState(InventarioAFechaExport $export, array $bodegas, array $kardexData): void
    {
        $ref = new \ReflectionClass($export);

        $bodegasProp = $ref->getProperty('bodegas');
        $bodegasProp->setAccessible(true);
        $bodegasProp->setValue($export, collect($bodegas));

        $kardexProp = $ref->getProperty('kardexData');
        $kardexProp->setAccessible(true);
        $kardexProp->setValue($export, $kardexData);
    }

    private function productoStub(int $id, array $inventarios): object
    {
        return (object) [
            'id' => $id,
            'nombre' => 'Cafe',
            'nombre_categoria' => 'Bebidas',
            'codigo' => 'C-1',
            'costo' => 1.5,
            'imagenes_count' => 0,
            'nombre_variante' => null,
            'empresa' => null,
            'inventarios' => collect($inventarios),
        ];
    }
}
