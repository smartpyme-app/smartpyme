<?php

namespace Tests\Unit\Services\Dte;

use App\Services\Dte\DteProcesoOpciones;
use InvalidArgumentException;
use Tests\TestCase;

class DteProcesoOpcionesTest extends TestCase
{
    public function test_omite_compra_pendiente_sin_mappings(): void
    {
        $opciones = DteProcesoOpciones::fromArray([]);

        $this->assertTrue($opciones->omitirCompraPendienteClasificacion('pendiente_clasificacion'));
        $this->assertFalse($opciones->omitirCompraPendienteClasificacion('pending'));
    }

    public function test_no_omite_pendiente_si_hay_mappings(): void
    {
        $opciones = DteProcesoOpciones::fromArray([
            'line_mappings' => [
                ['index' => 0, 'id_producto' => 12, 'cantidad' => 2],
            ],
        ]);

        $this->assertFalse($opciones->omitirCompraPendienteClasificacion('pendiente_clasificacion'));
    }

    public function test_estado_compra_y_gasto_segun_credito(): void
    {
        $contado = DteProcesoOpciones::fromArray(['credito' => false]);
        $this->assertSame('Pagada', $contado->estadoCompra());
        $this->assertSame('Confirmado', $contado->estadoGasto());

        $credito = DteProcesoOpciones::fromArray(['credito' => true, 'fecha_pago' => '2026-09-15']);
        $this->assertSame('Pendiente', $credito->estadoCompra());
        $this->assertSame('Pendiente', $credito->estadoGasto());
    }

    public function test_credito_sin_fecha_pago_falla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DteProcesoOpciones::fromArray(['credito' => true])->validarPago();
    }

    public function test_transferencia_sin_banco_falla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DteProcesoOpciones::fromArray(['forma_pago' => 'Transferencia'])->validarPago();
    }

    public function test_efectivo_no_pide_banco(): void
    {
        DteProcesoOpciones::fromArray(['forma_pago' => 'Efectivo'])->validarPago();
        $this->assertTrue(true);
    }

    public function test_validar_lineas_compra_exige_producto_y_cantidad(): void
    {
        $opciones = DteProcesoOpciones::fromArray([
            'line_mappings' => [
                ['index' => 0, 'id_producto' => 1, 'cantidad' => 2],
            ],
        ]);
        $opciones->validarLineasCompra(1);

        $this->expectException(InvalidArgumentException::class);
        $opciones->validarLineasCompra(2);
    }

    public function test_cantidad_cero_falla(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DteProcesoOpciones::fromArray([
            'line_mappings' => [
                ['index' => 0, 'id_producto' => 1, 'cantidad' => 0],
            ],
        ])->validarLineasCompra(1);
    }

    public function test_mapping_por_index(): void
    {
        $opciones = DteProcesoOpciones::fromArray([
            'line_mappings' => [
                ['index' => 1, 'id_producto' => 9, 'cantidad' => 4.5],
            ],
        ]);

        $porIndex = $opciones->mappingPorIndex();
        $this->assertSame(9, $porIndex[1]['id_producto']);
        $this->assertSame(4.5, $porIndex[1]['cantidad']);
    }

    public function test_pago_sugerido_desde_json(): void
    {
        $sugerido = DteProcesoOpciones::pagoSugeridoDesdeJson([
            'resumen' => [
                'pagos' => [['codigo' => '05']],
                'condicionOperacion' => 1,
            ],
        ]);

        $this->assertSame('Transferencia', $sugerido['forma_pago']);
        $this->assertFalse($sugerido['credito']);
    }

    public function test_pago_sugerido_credito_por_codigo_o_condicion(): void
    {
        $porCodigo = DteProcesoOpciones::pagoSugeridoDesdeJson([
            'resumen' => ['pagos' => [['codigo' => '06']]],
        ]);
        $this->assertTrue($porCodigo['credito']);
        $this->assertSame('Crédito', $porCodigo['forma_pago']);

        $porCondicion = DteProcesoOpciones::pagoSugeridoDesdeJson([
            'resumen' => ['condicionOperacion' => 2],
        ]);
        $this->assertTrue($porCondicion['credito']);
        $this->assertSame('Efectivo', $porCondicion['forma_pago']);
    }
}
