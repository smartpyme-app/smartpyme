<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\Admin\Impuesto;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Devoluciones\Detalle as DetalleDevolucion;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaInvoiceFromVentaMapper;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CostaRicaInvoiceFromVentaMapperTest extends TestCase
{
    private CostaRicaInvoiceFromVentaMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new CostaRicaInvoiceFromVentaMapper(new CostaRicaTipoCambioService());
    }

    public function test_fecha_emision_xml_usa_hora_real_costa_rica(): void
    {
        $iso = $this->mapper->fechaEmisionXmlCr();
        $this->assertMatchesRegularExpression('/-06:00$/', $iso);

        $dt = Carbon::parse($iso)->timezone('America/Costa_Rica');
        $now = Carbon::now('America/Costa_Rica');
        $this->assertLessThanOrEqual(3, abs($now->diffInSeconds($dt)));
        $this->assertNotSame('00:00:00', $dt->format('H:i:s'));
    }

    public function test_linea_devolucion_iva_es_base_imponible_por_tarifa(): void
    {
        $empresa = $this->empresaStub();
        $detalle = $this->detalleDevolucionMock(2, 100.0, 10.0, '8313100000100', 'Servicio');

        $line = $this->mapper->lineaDesdeDetalleDevolucion($detalle, $empresa, 13.0);

        $base = 190.0;
        $ivaEsperado = round($base * 0.13, 5);
        $this->assertSame($ivaEsperado, $line['taxes'][0]['amount']);
        $this->assertSame($base, $line['taxable_base']);
        $this->assertSame(round($base + $ivaEsperado, 5), $line['total']);
        $this->assertSame('Sp', $line['unit_measure']);
    }

    public function test_resumen_devolucion_separa_servicios_y_mercancias_y_desglose(): void
    {
        $empresa = $this->empresaStub();

        $detalleServicio = $this->detalleDevolucionMock(1, 100.0, 0.0, '8313100000100', 'Servicio');
        $detalleMercancia = $this->detalleDevolucionMock(1, 200.0, 0.0, '8517120000100', 'Mercancía');

        $lineServicio = $this->mapper->lineaDesdeDetalleDevolucion($detalleServicio, $empresa, 13.0);
        $lineMercancia = $this->mapper->lineaDesdeDetalleDevolucion($detalleMercancia, $empresa, 13.0);

        $devolucion = (new \ReflectionClass(Devolucion::class))->newInstanceWithoutConstructor();
        $devolucion->sub_total = 300.0;
        $devolucion->iva = 39.0;
        $devolucion->exenta = 0.0;
        $devolucion->no_sujeta = 0.0;
        $devolucion->total = 339.0;
        $devolucion->setRelation('detalles', new Collection([$detalleServicio, $detalleMercancia]));

        $summary = $this->mapper->resumenDevolucionAlineadoLineas($devolucion, [$lineServicio, $lineMercancia]);

        $this->assertSame(200.0, $summary['total_taxed_goods']);
        $this->assertSame(100.0, $summary['total_taxed_services']);
        $this->assertSame(300.0, $summary['total_taxed']);
        $this->assertNotEmpty($summary['taxes']);
        $this->assertSame('01', $summary['taxes'][0]['tax_type']);
        $this->assertSame('08', $summary['taxes'][0]['iva_type']);
        $this->assertSame(39.0, $summary['taxes'][0]['amount']);
    }

    public function test_multiimpuesto_13_mas_5_emite_solo_iva_13_tarifa_08(): void
    {
        $empresa = $this->empresaStub();
        $detalle = $this->detalleDevolucionMock(1, 100.0, 0.0, '8517120000100', 'Producto');
        $detalle->producto->setRelation('impuestos', new Collection([
            $this->impuestoStub(13.0, null),
            $this->impuestoStub(5.0, 'C8'),
        ]));

        // Fallback 18% (suma multiimpuesto): debe ignorarse y usar IVA 13 del producto.
        $line = $this->mapper->lineaDesdeDetalleDevolucion($detalle, $empresa, 18.0);

        $this->assertCount(1, $line['taxes']);
        $this->assertSame('01', $line['taxes'][0]['tax_type']);
        $this->assertSame('08', $line['taxes'][0]['iva_type']);
        $this->assertSame(13.0, $line['taxes'][0]['rate']);
        $this->assertSame(13.0, $line['taxes'][0]['amount']);
    }

    public function test_porcentaje_18_sin_impuestos_producto_falla(): void
    {
        $empresa = $this->empresaStub();
        $detalle = $this->detalleDevolucionMock(1, 100.0, 0.0, '8517120000100', 'Producto');
        $detalle->producto->setRelation('impuestos', new Collection());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/posible suma multiimpuesto/');

        $this->mapper->lineaDesdeDetalleDevolucion($detalle, $empresa, 18.0);
    }

    public function test_tarifa_iva_1_por_ciento_codigo_02(): void
    {
        $empresa = $this->empresaStub();
        $detalle = $this->detalleDevolucionMock(1, 100.0, 0.0, '8517120000100', 'Producto');
        $detalle->producto->setRelation('impuestos', new Collection([
            $this->impuestoStub(1.0, null),
        ]));

        $line = $this->mapper->lineaDesdeDetalleDevolucion($detalle, $empresa, 1.0);

        $this->assertSame('02', $line['taxes'][0]['iva_type']);
        $this->assertSame(1.0, $line['taxes'][0]['rate']);
        $this->assertSame(1.0, $line['taxes'][0]['amount']);
    }

    private function empresaStub(): Empresa
    {
        $empresa = $this->createMock(Empresa::class);
        $empresa->method('getCustomConfigValue')->willReturnCallback(
            static function (string $group, string $key, $default = null) {
                if ($key === 'cabys_default') {
                    return '8517120000100';
                }

                return $default;
            }
        );
        $empresa->moneda = 'CRC';

        return $empresa;
    }

    private function detalleDevolucionMock(
        float $cantidad,
        float $precio,
        float $descuento,
        string $cabys,
        string $tipoProducto
    ): DetalleDevolucion {
        $producto = (new \ReflectionClass(Producto::class))->newInstanceWithoutConstructor();
        $producto->nombre = 'Ítem '.$tipoProducto;
        $producto->tipo = $tipoProducto;
        $producto->codigo = null;
        $producto->setRawAttributes(['codigo_cabys' => $cabys]);
        $producto->setRelation('impuestos', new Collection());

        $detalle = (new \ReflectionClass(DetalleDevolucion::class))->newInstanceWithoutConstructor();
        $detalle->cantidad = $cantidad;
        $detalle->precio = $precio;
        $detalle->descuento = $descuento;
        $detalle->total = round(($precio * $cantidad - $descuento) * 1.13, 2);
        $detalle->descripcion = $tipoProducto;
        $detalle->exenta = 0;
        $detalle->no_sujeta = 0;
        $detalle->tipo_gravado = null;
        $detalle->setRelation('producto', $producto);

        return $detalle;
    }

    private function impuestoStub(float $porcentaje, ?string $codigoMh): Impuesto
    {
        $imp = (new \ReflectionClass(Impuesto::class))->newInstanceWithoutConstructor();
        $imp->porcentaje = $porcentaje;
        $imp->codigo_mh = $codigoMh;

        return $imp;
    }
}
