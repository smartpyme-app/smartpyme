<?php

namespace Tests\Unit\Services\Inventario;

use App\Services\Inventario\HistorialPrecioCostoService;
use PHPUnit\Framework\TestCase;

class HistorialPrecioCostoServiceTest extends TestCase
{
    private HistorialPrecioCostoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HistorialPrecioCostoService();
    }

    public function test_varias_compras_con_costos_distintos_actualizan_la_tendencia(): void
    {
        $resultado = $this->service->construir(
            $this->producto(['costo' => 12.0]),
            '2026-08-01',
            [],
            [
                $this->compra(['fecha' => '2026-08-05', 'costo' => 8.0, 'id' => 1]),
                $this->compra(['fecha' => '2026-08-20', 'costo' => 12.0, 'id' => 2]),
            ]
        );

        $this->assertSame('subiendo', $resultado['tendencia_costo']);
        $this->assertEqualsWithDelta(50.0, $resultado['variacion_costo'], 0.01);
        $this->assertCount(2, array_filter($resultado['eventos'], fn ($e) => $e['tipo'] === 'costo'));
        $this->assertEqualsWithDelta(12.0, $resultado['producto']['costo'], 0.0001);
    }

    public function test_dos_compras_el_mismo_dia_con_costo_distinto_no_se_colapsan(): void
    {
        $resultado = $this->service->construir(
            $this->producto(['costo' => 15.0]),
            '2026-08-01',
            [],
            [
                $this->compra(['fecha' => '2026-08-15', 'costo' => 10.0, 'id' => 1]),
                $this->compra(['fecha' => '2026-08-15', 'costo' => 15.0, 'id' => 2]),
            ]
        );

        $costosEvento = array_values(array_map(
            fn ($e) => $e['valor'],
            array_filter($resultado['eventos'], fn ($e) => $e['tipo'] === 'costo')
        ));
        $costosSerie = array_values(array_filter($resultado['costos'], fn ($v) => $v !== null));

        $this->assertSame([10.0, 15.0], $costosEvento);
        $this->assertContains(10.0, $costosSerie);
        $this->assertContains(15.0, $costosSerie);
        $this->assertSame('subiendo', $resultado['tendencia_costo']);
        $this->assertEqualsWithDelta(50.0, $resultado['variacion_costo'], 0.01);
    }

    public function test_tendencia_usa_el_costo_de_apertura_no_la_serie_rellenada(): void
    {
        $resultado = $this->service->construir(
            $this->producto(['costo' => 10.0]),
            '2026-08-01',
            [
                $this->venta(['fecha' => '2026-08-10', 'precio' => 20.0]),
                $this->venta(['fecha' => '2026-08-25', 'precio' => 22.0]),
            ],
            [
                $this->compra(['fecha' => '2026-08-12', 'costo' => 10.0, 'id' => 1]),
                $this->compra(['fecha' => '2026-08-18', 'costo' => 10.0, 'id' => 2]),
            ],
            20.0,
            8.0
        );

        $this->assertSame('subiendo', $resultado['tendencia_costo']);
        $this->assertEqualsWithDelta(25.0, $resultado['variacion_costo'], 0.01);
        $this->assertEqualsWithDelta(10.0, $resultado['producto']['costo'], 0.0001);
        $this->assertSame(8.0, $resultado['costos'][0]);
    }

    public function test_costo_usa_neto_por_unidad_base_con_descuento(): void
    {
        $resultado = $this->service->construir(
            $this->producto(['costo' => 8.0]),
            '2026-08-01',
            [],
            [
                $this->compra([
                    'fecha' => '2026-08-10',
                    'costo' => 10.0,
                    'cantidad' => 10,
                    'descuento' => 20,
                    'id' => 1,
                ]),
            ],
            null,
            10.0
        );

        $eventoCosto = $this->primerEvento($resultado['eventos'], 'costo');

        $this->assertEqualsWithDelta(8.0, $eventoCosto['valor'], 0.0001);
        $this->assertSame('bajando', $resultado['tendencia_costo']);
        $this->assertEqualsWithDelta(-20.0, $resultado['variacion_costo'], 0.01);
        $this->assertEqualsWithDelta(8.0, $resultado['producto']['costo'], 0.0001);
    }

    public function test_costo_con_presentacion_reparte_el_neto_en_unidades_base(): void
    {
        $resultado = $this->service->construir(
            $this->producto(['costo' => 3.6]),
            '2026-08-01',
            [],
            [
                $this->compra([
                    'fecha' => '2026-08-10',
                    'costo' => 120.0,
                    'cantidad' => 2,
                    'descuento' => 24,
                    'factor_conversion' => 30,
                    'id' => 1,
                ]),
            ]
        );

        $eventoCosto = $this->primerEvento($resultado['eventos'], 'costo');

        $this->assertEqualsWithDelta(3.6, $eventoCosto['valor'], 0.0001);
        $this->assertEqualsWithDelta(3.6, $resultado['producto']['costo'], 0.0001);
    }

    public function test_mismo_costo_neto_que_la_apertura_queda_estable(): void
    {
        $resultado = $this->service->construir(
            $this->producto(['costo' => 10.0]),
            '2026-08-01',
            [],
            [
                $this->compra(['fecha' => '2026-08-10', 'costo' => 10.0, 'id' => 1]),
                $this->compra(['fecha' => '2026-08-20', 'costo' => 10.0, 'id' => 2]),
            ],
            null,
            10.0
        );

        $this->assertSame('estable', $resultado['tendencia_costo']);
        $this->assertEqualsWithDelta(0.0, $resultado['variacion_costo'], 0.01);
    }

    public function test_costo_actual_es_el_ultimo_costo_neto_del_periodo(): void
    {
        $resultado = $this->service->construir(
            $this->producto(['costo' => 99.0]),
            '2026-08-01',
            [],
            [
                $this->compra(['fecha' => '2026-08-05', 'costo' => 7.0, 'id' => 1]),
                $this->compra(['fecha' => '2026-08-28', 'costo' => 9.5, 'id' => 2]),
            ]
        );

        $this->assertEqualsWithDelta(9.5, $resultado['producto']['costo'], 0.0001);
        $this->assertCount(2, $resultado['eventos']);
        $this->assertSame(2, $resultado['total_compras']);
    }

    private function primerEvento(array $eventos, string $tipo): array
    {
        foreach ($eventos as $evento) {
            if ($evento['tipo'] === $tipo) {
                return $evento;
            }
        }

        $this->fail("No hay evento de tipo {$tipo}");
    }

    private function producto(array $overrides = []): array
    {
        return array_merge([
            'id' => 201,
            'nombre' => 'Producto prueba',
            'codigo' => 'P-201',
            'precio' => 20.0,
            'costo' => 10.0,
        ], $overrides);
    }

    private function compra(array $overrides = []): array
    {
        return array_merge([
            'fecha' => '2026-08-15',
            'costo' => 10.0,
            'cantidad' => 1,
            'descuento' => 0,
            'factor_conversion' => 1,
            'id' => 1,
            'id_documento' => 50,
            'referencia' => 'F-001',
            'usuario' => 'Luis',
        ], $overrides);
    }

    private function venta(array $overrides = []): array
    {
        return array_merge([
            'fecha' => '2026-08-10',
            'precio' => 20.0,
            'precio_sin_iva' => 20.0,
            'id' => 1,
            'id_documento' => 80,
            'correlativo' => 80,
            'usuario' => 'Ana',
        ], $overrides);
    }
}
