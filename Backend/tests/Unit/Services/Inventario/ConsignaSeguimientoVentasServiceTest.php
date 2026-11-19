<?php

namespace Tests\Unit\Services\Inventario;

use App\Models\Admin\Empresa;
use App\Services\Inventario\ConsignaSeguimientoVentasService;
use Illuminate\Http\Request;
use Tests\TestCase;

class ConsignaSeguimientoVentasServiceTest extends TestCase
{
    public function test_nombre_cliente_inexistente_usa_fallback(): void
    {
        $service = new ConsignaSeguimientoVentasService(
            $this->createStub(\App\Services\Inventario\ConsignaHistorialService::class),
            $this->createStub(\App\Services\Inventario\ConsignaProductosVentaListadoService::class),
        );

        $ref = new \ReflectionMethod(ConsignaSeguimientoVentasService::class, 'nombreCliente');
        $ref->setAccessible(true);

        $this->assertSame('Cliente #999999', $ref->invoke($service, 999999));
    }

    public function test_secciones_toma_inventario_omite_clientes_sin_filas(): void
    {
        $listado = $this->createMock(\App\Services\Inventario\ConsignaProductosVentaListadoService::class);
        $listado->method('agruparPorProductoYLote')->willReturn(false);
        $listado->method('filasTomaInventarioPorCliente')
            ->willReturnCallback(fn (int $idCliente) => $idCliente === 1
                ? [['codigo' => 'Y', 'disponible' => 3, 'id_producto' => 10]]
                : []);

        $service = new ConsignaSeguimientoVentasService(
            $this->createStub(\App\Services\Inventario\ConsignaHistorialService::class),
            $listado,
        );

        $empresa = new Empresa;
        $empresa->id = 1;

        $out = $service->seccionesTomaInventario(
            Request::create('/', 'GET', ['id_cliente' => 1]),
            1,
            $empresa
        );

        $this->assertCount(1, $out['secciones']);
        $this->assertSame(1, $out['secciones'][0]['cliente_id']);
        $this->assertFalse($out['secciones'][0]['payload']['incluir_lote']);
        $this->assertSame('Y', $out['secciones'][0]['payload']['resumen'][0]['codigo']);
    }
}