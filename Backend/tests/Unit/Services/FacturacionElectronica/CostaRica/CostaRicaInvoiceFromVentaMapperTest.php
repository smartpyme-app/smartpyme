<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\Admin\Impuesto;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Devoluciones\Detalle as DetalleDevolucion;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use App\Services\FacturacionElectronica\CostaRica\BccrTipoCambioClient;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaFeEmitService;
use App\Support\FacturacionElectronica\CostaRica\ReferenciaComprobanteCr;
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
        $this->mapper = new CostaRicaInvoiceFromVentaMapper(
            new CostaRicaTipoCambioService($this->createMock(BccrTipoCambioClient::class))
        );
    }

    public function test_factura_sin_sustitucion_ni_rechazo_no_lleva_referencia(): void
    {
        $refs = $this->mapper->referencedDocumentsSustitucionORechazo($this->ventaAtributos([]));

        self::assertSame([], $refs);
    }

    public function test_despues_de_anular_con_13_la_factura_referencia_15(): void
    {
        $clave = str_repeat('A', 50);
        $refs = $this->mapper->referencedDocumentsSustitucionORechazo($this->ventaAtributos([
            'clave_sustituida' => $clave,
            'codigo_nota_anulacion' => '13',
            'fecha' => '2026-09-02 10:00:00',
        ]));

        self::assertCount(1, $refs);
        self::assertSame('01', $refs[0]['document_type']);
        self::assertSame($clave, $refs[0]['document_number']);
        self::assertSame('15', $refs[0]['referenced_code']);
        self::assertStringStartsWith('2026-09-02T', $refs[0]['emission_date']);
    }

    public function test_tiquete_sustituido_usa_tipo_04_y_la_fecha_sale_de_la_clave(): void
    {
        $clave = '506150826'.str_repeat('1', 41);
        $attrs = [
            'clave_sustituida' => $clave,
            'codigo_nota_anulacion' => '01',
            'fecha' => '2026-11-02 10:00:00',
        ];

        $factura = $this->mapper->referencedDocumentsSustitucionORechazo($this->ventaAtributos($attrs));
        self::assertSame('01', $factura[0]['document_type']);
        self::assertSame('07', $factura[0]['referenced_code']);
        self::assertSame('2026-08-15T00:00:00-06:00', $factura[0]['emission_date']);

        $tiquete = $this->mapper->referencedDocumentsSustitucionORechazo($this->ventaAtributos($attrs), true);
        self::assertSame('04', $tiquete[0]['document_type']);
        self::assertSame('07', $tiquete[0]['referenced_code']);
        self::assertSame('2026-08-15T00:00:00-06:00', $tiquete[0]['emission_date']);
    }

    public function test_despues_de_anular_con_01_la_factura_referencia_07(): void
    {
        $clave = str_repeat('7', 50);
        $refs = $this->mapper->referencedDocumentsSustitucionORechazo($this->ventaAtributos([
            'clave_sustituida' => $clave,
            'codigo_nota_anulacion' => '01',
        ]));

        self::assertCount(1, $refs);
        self::assertSame('07', $refs[0]['referenced_code']);
        self::assertSame('01', $refs[0]['document_type']);
    }

    public function test_fee_sustituida_usa_tipo_19_en_referencia(): void
    {
        $clave = str_repeat('E', 50);
        $refs = $this->mapper->referencedDocumentsSustitucionORechazo($this->ventaAtributos([
            'nombre_documento' => 'Factura de exportación',
            'clave_sustituida' => $clave,
            'codigo_nota_anulacion' => '13',
        ]));

        self::assertCount(1, $refs);
        self::assertSame(ReferenciaComprobanteCr::TIPO_EXPORTACION, $refs[0]['document_type']);
        self::assertSame('15', $refs[0]['referenced_code']);
    }

    public function test_clave_sustituida_que_no_tiene_50_caracteres_no_referencia(): void
    {
        $refs = $this->mapper->referencedDocumentsSustitucionORechazo($this->ventaAtributos([
            'clave_sustituida' => str_repeat('1', 49),
            'codigo_nota_anulacion' => '13',
        ]));

        self::assertSame([], $refs);
    }

    public function test_renumera_correlativo_solo_si_rechazado_y_sin_clave_aceptada(): void
    {
        $clave = '50631012600310123456700100001010000000001199999999';

        self::assertTrue(CostaRicaFeEmitService::debeRenumerarCorrelativoTrasRechazo(true, $clave, false));
        self::assertFalse(CostaRicaFeEmitService::debeRenumerarCorrelativoTrasRechazo(true, '', false));
        self::assertFalse(CostaRicaFeEmitService::debeRenumerarCorrelativoTrasRechazo(true, '   ', false));
        self::assertFalse(CostaRicaFeEmitService::debeRenumerarCorrelativoTrasRechazo(true, $clave, true));
        self::assertFalse(CostaRicaFeEmitService::debeRenumerarCorrelativoTrasRechazo(false, $clave, false));
    }

    public function test_reemision_de_rechazado_referencia_tipo_10_codigo_16(): void
    {
        $clave = '50631012600310123456700100001010000000001199999999';
        $refs = $this->mapper->referencedDocumentsSustitucionORechazo($this->ventaAtributos([
            'clave_rechazada' => $clave,
        ]));

        $esperada = ReferenciaComprobanteCr::referenciaDeRechazado($clave);
        self::assertCount(1, $refs);
        self::assertSame($esperada['document_type'], $refs[0]['document_type']);
        self::assertSame($esperada['referenced_code'], $refs[0]['referenced_code']);
        self::assertSame($esperada['document_number'], $refs[0]['document_number']);
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

    public function test_factor_crc_empresa_usd_documento_divide_por_tipo_cambio(): void
    {
        $empresa = $this->empresaStub();
        $empresa->moneda = 'CRC';

        $ref = new \ReflectionMethod(CostaRicaInvoiceFromVentaMapper::class, 'factorMontosEmpresaADocumentoFe');
        $ref->setAccessible(true);

        $factor = $ref->invoke($this->mapper, $empresa, 'USD', 454.06);
        $this->assertEqualsWithDelta(1 / 454.06, $factor, 1e-12);
        $this->assertSame(1.0, $ref->invoke($this->mapper, $empresa, 'CRC', 454.06));
    }

    public function test_convertir_montos_linea_fe_aplica_factor(): void
    {
        $ref = new \ReflectionMethod(CostaRicaInvoiceFromVentaMapper::class, 'convertirMontosLineaFe');
        $ref->setAccessible(true);

        $line = [
            'unit_price' => 4424.78,
            'sub_total' => 4424.78,
            'total_amount' => 4424.78,
            'taxable_base' => 4424.78,
            'total_tax' => 575.22,
            'total' => 5000.0,
            'taxes' => [['amount' => 575.22]],
        ];
        $factor = 1 / 454.06;
        $out = $ref->invoke($this->mapper, $line, $factor);

        $this->assertEqualsWithDelta(5000.0 / 454.06, $out['total'], 0.0001);
        $this->assertEqualsWithDelta(575.22 / 454.06, $out['taxes'][0]['amount'], 0.0001);
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

    public function test_receptor_empresa_no_copia_direccion_telefono_ni_correo_del_emisor(): void
    {
        $empresa = $this->empresaStub();
        $empresa->direccion = 'Barva Centro';
        $empresa->telefono = '22222222';
        $empresa->correo = 'ventas@grupoteccr.com';

        $cliente = (new \ReflectionClass(Cliente::class))->newInstanceWithoutConstructor();
        $cliente->tipo = 'Empresa';
        $cliente->tipo_documento = '02';
        $cliente->nit = '3102666312';
        $cliente->nombre_empresa = 'Condominio Vereda del Café';
        $cliente->direccion = null;
        $cliente->telefono = null;
        $cliente->empresa_direccion = 'Veredas del Café, Santa Bárbara';
        $cliente->empresa_telefono = '88881234';
        $cliente->correo = 'admin@veredacafe.cr';
        $cliente->cod_distrito = '40401';
        $cliente->distrito = 'Santa Bárbara';
        $cliente->municipio = 'Santa Bárbara';

        $venta = (new \ReflectionClass(Venta::class))->newInstanceWithoutConstructor();
        $venta->setRelation('cliente', $cliente);

        $receiver = $this->mapper->receptorDatosVenta($venta, $empresa);

        $this->assertSame('40401', $receiver['location']['district']);
        $this->assertSame(4, $receiver['location']['province']);
        $this->assertSame('404', $receiver['location']['canton']);
        $this->assertSame('Veredas del Café, Santa Bárbara', $receiver['location']['address_details']);
        $this->assertSame('Santa Bárbara', $receiver['location']['neighborhood']);
        $this->assertSame('88881234', $receiver['phone']['number']);
        $this->assertSame(['admin@veredacafe.cr'], $receiver['email']);
    }

    public function test_receptor_nombre_se_trunca_a_100_caracteres_xsd(): void
    {
        $empresa = $this->empresaStub();
        $largo = 'SUB CONDOMINIO VERTICAL HORIZONTAL RESIDENCIAL VEREDA DEL CAFE EN FINCA FILIAL PRIMARIA INDIVIDUALIZADA NUMERO VEINTIOCHO';
        $this->assertGreaterThan(100, mb_strlen($largo));

        $cliente = (new \ReflectionClass(Cliente::class))->newInstanceWithoutConstructor();
        $cliente->tipo = 'Empresa';
        $cliente->tipo_documento = '02';
        $cliente->nit = '3102666312';
        $cliente->nombre_empresa = $largo;

        $venta = (new \ReflectionClass(Venta::class))->newInstanceWithoutConstructor();
        $venta->setRelation('cliente', $cliente);

        $receiver = $this->mapper->receptorDatosVenta($venta, $empresa);

        $this->assertSame(100, mb_strlen($receiver['name']));
        $this->assertSame(mb_substr($largo, 0, 100), $receiver['name']);
    }

    public function test_receptor_sin_contacto_no_copia_ubicacion_telefono_ni_correo_del_emisor(): void
    {
        $empresa = $this->empresaStub();
        $empresa->direccion = 'Barva Centro';
        $empresa->telefono = '22222222';
        $empresa->correo = 'ventas@grupoteccr.com';

        $cliente = (new \ReflectionClass(Cliente::class))->newInstanceWithoutConstructor();
        $cliente->tipo = 'Empresa';
        $cliente->tipo_documento = '02';
        $cliente->nit = '3102666312';
        $cliente->nombre_empresa = 'Condominio Vereda del Café';
        $cliente->direccion = null;
        $cliente->telefono = null;
        $cliente->empresa_direccion = null;
        $cliente->empresa_telefono = null;
        $cliente->correo = null;
        $cliente->cod_distrito = null;

        $venta = (new \ReflectionClass(Venta::class))->newInstanceWithoutConstructor();
        $venta->setRelation('cliente', $cliente);

        $receiver = $this->mapper->receptorDatosVenta($venta, $empresa);

        $this->assertArrayNotHasKey('location', $receiver);
        $this->assertArrayNotHasKey('phone', $receiver);
        $this->assertArrayNotHasKey('email', $receiver);
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

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function ventaAtributos(array $attrs): Venta
    {
        $venta = (new \ReflectionClass(Venta::class))->newInstanceWithoutConstructor();
        $venta->setRawAttributes($attrs, true);

        return $venta;
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
