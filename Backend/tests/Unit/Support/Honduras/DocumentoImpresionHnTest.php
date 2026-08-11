<?php

namespace Tests\Unit\Support\Honduras;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Support\Honduras\DocumentoImpresionHn;
use Tests\TestCase;

final class DocumentoImpresionHnTest extends TestCase
{
    public function test_aplica_a_los_nueve_documentos_fiscales_hn(): void
    {
        $empresa = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN']);
        $this->assertCount(9, DocumentoImpresionHn::NOMBRES_FISCALES);
        foreach (DocumentoImpresionHn::NOMBRES_FISCALES as $nombre) {
            $this->assertTrue(DocumentoImpresionHn::aplica($empresa, new Documento(['nombre' => $nombre])));
        }
    }

    public function test_no_aplica_a_otro_pais_ni_documento_operativo(): void
    {
        $hn = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN']);
        $sv = new Empresa(['pais' => 'El Salvador', 'cod_pais' => 'SV']);
        $cr = new Empresa(['pais' => 'Costa Rica', 'cod_pais' => 'CR']);
        $factura = new Documento(['nombre' => 'Factura sin RTN']);

        $this->assertFalse(DocumentoImpresionHn::aplica($hn, new Documento(['nombre' => 'Cotización'])));
        $this->assertFalse(DocumentoImpresionHn::aplica($hn, new Documento(['nombre' => 'Factura'])));
        $this->assertFalse(DocumentoImpresionHn::aplica($sv, $factura));
        $this->assertFalse(DocumentoImpresionHn::aplica($cr, $factura));
    }

    public function test_mapa_de_plantillas_de_factura_hn_es_exacto(): void
    {
        $this->assertSame([
            420 => 'reportes.facturacion.formatos_empresas.Factura-Inversiones-Andre',
            614 => 'reportes.facturacion.formatos_empresas.Factura-Accesorios-Honduras',
            700 => 'reportes.facturacion.formatos_empresas.Factura-Lilian-Ohle',
        ], DocumentoImpresionHn::VISTAS_FACTURA_EMPRESA);
    }

    public function test_resuelve_la_plantilla_de_empresa_solo_para_facturas_hn(): void
    {
        foreach (DocumentoImpresionHn::VISTAS_FACTURA_EMPRESA as $idEmpresa => $vista) {
            foreach (['Factura con RTN', 'Factura sin RTN'] as $nombre) {
                $this->assertSame($vista, DocumentoImpresionHn::resolverVista(
                    $this->empresaHn($idEmpresa),
                    new Documento(['nombre' => $nombre])
                ));
            }
        }

        $this->assertSame(
            DocumentoImpresionHn::VISTA_DEFAULT,
            DocumentoImpresionHn::resolverVista($this->empresaHn(1), new Documento(['nombre' => 'Factura sin RTN']))
        );
        $this->assertNull(DocumentoImpresionHn::resolverVista(
            new Empresa(['pais' => 'El Salvador', 'cod_pais' => 'SV']),
            new Documento(['nombre' => 'Factura sin RTN'])
        ));
    }

    public function test_documentos_hn_que_no_son_factura_ignoran_la_plantilla_de_empresa(): void
    {
        $empresa = $this->empresaHn(614);

        foreach ([
            'Boleta de compra',
            'Nota de crédito',
            'Nota de débito',
            'Recibo por honorarios profesionales',
            'Guía de remisión',
            'Comprobante de retención',
        ] as $nombre) {
            $documento = new Documento(['nombre' => $nombre]);

            $this->assertNull(DocumentoImpresionHn::vistaFacturaEmpresa($empresa, $documento));
            $this->assertSame(
                DocumentoImpresionHn::VISTA_DEFAULT,
                DocumentoImpresionHn::resolverVista($empresa, $documento)
            );
        }
    }

    private function empresaHn(int $id): Empresa
    {
        $empresa = new Empresa(['pais' => 'Honduras', 'cod_pais' => 'HN']);
        $empresa->id = $id;

        return $empresa;
    }

    public function test_es_factura_solo_reconoce_nombres_de_factura(): void
    {
        foreach (['Factura', 'Factura con RTN', 'Factura sin RTN'] as $nombre) {
            $this->assertTrue(DocumentoImpresionHn::esFactura($nombre));
        }

        foreach ([
            'Ticket',
            'Boleta de compra',
            'Nota de crédito',
            'Nota de débito',
            'Recibo por honorarios profesionales',
            'Guía de remisión',
            'Comprobante de retención',
            null,
        ] as $nombre) {
            $this->assertFalse(DocumentoImpresionHn::esFactura($nombre));
        }
    }

    public function test_ticket_accesorios_aplica_por_flag_o_empresa_716_solo_a_facturas_hn(): void
    {
        $conFlag = $this->empresaHn(1);
        $conFlag->custom_empresa = [
            'configuraciones' => ['factura_ticket_accesorios_hn' => true],
        ];
        $empresa716 = $this->empresaHn(716);

        foreach (['Factura', 'Factura con RTN', 'Factura sin RTN'] as $nombre) {
            $this->assertTrue(DocumentoImpresionHn::usaTicketAccesorios($conFlag, $nombre));
            $this->assertTrue(DocumentoImpresionHn::usaTicketAccesorios($empresa716, $nombre));
        }
    }

    public function test_ticket_accesorios_no_aplica_a_documentos_no_factura_ni_fuera_de_honduras(): void
    {
        $empresa716 = $this->empresaHn(716);
        $empresa716->custom_empresa = [
            'configuraciones' => ['factura_ticket_accesorios_hn' => true],
        ];
        $sv = new Empresa(['pais' => 'El Salvador', 'cod_pais' => 'SV']);
        $sv->id = 716;
        $sv->custom_empresa = $empresa716->custom_empresa;

        foreach (['Ticket', 'Guía de remisión', 'Nota de crédito', 'Boleta de compra'] as $nombre) {
            $this->assertFalse(DocumentoImpresionHn::usaTicketAccesorios($empresa716, $nombre));
        }
        $this->assertFalse(DocumentoImpresionHn::usaTicketAccesorios($sv, 'Factura con RTN'));
        $this->assertFalse(DocumentoImpresionHn::usaTicketAccesorios($this->empresaHn(1), 'Factura sin RTN'));
    }

    public function test_centavos_devuelve_siempre_dos_digitos(): void
    {
        $this->assertSame('00', DocumentoImpresionHn::centavos(426));
        $this->assertSame('50', DocumentoImpresionHn::centavos(1234.5));
        $this->assertSame('07', DocumentoImpresionHn::centavos(0.07));
        $this->assertSame('99', DocumentoImpresionHn::centavos('12345.99'));
    }

    public function test_formatea_correlativo_y_footer_sin_inventar_valores(): void
    {
        $documento = new Documento([
            'numero_emision' => '01',
            'nota' => "Línea 1\nLínea 2",
            'resolucion' => 'CAI-123',
            'rangos' => '001-001-01-00000001 A 001-001-01-00003000',
            'fecha' => '2027-05-23',
        ]);

        $this->assertSame('001-001-01-00000439', DocumentoImpresionHn::correlativo($documento, 439));
        $this->assertSame('CAI-123', DocumentoImpresionHn::footer($documento)['cai']);
        $this->assertNull(DocumentoImpresionHn::footer(new Documento())['cai']);
    }

    public function test_calcula_totales_fiscales_y_conserva_ceros_para_valores_ausentes(): void
    {
        $detalles = [
            (object) [
                'tipo_gravado' => 'gravada',
                'porcentaje_impuesto' => 15,
                'gravada' => 100,
                'iva' => 15,
                'descuento' => 5,
            ],
            (object) [
                'tipo_gravado' => 'gravada',
                'porcentaje_impuesto' => 18,
                'sub_total' => 200,
                'iva' => 36,
            ],
            (object) [
                'tipo_gravado' => 'exenta',
                'exenta' => 50,
            ],
            (object) [
                'tipo_gravado' => 'exonerada',
                'total' => 75,
            ],
        ];

        $this->assertSame([
            'exonerado' => 75.0,
            'exento' => 50.0,
            'gravado_15' => 100.0,
            'gravado_18' => 200.0,
            'isv_15' => 15.0,
            'isv_18' => 36.0,
            'descuento' => 5.0,
        ], DocumentoImpresionHn::totales($detalles, 15));

        $this->assertSame([
            'exonerado' => 0.0,
            'exento' => 0.0,
            'gravado_15' => 0.0,
            'gravado_18' => 0.0,
            'isv_15' => 0.0,
            'isv_18' => 0.0,
            'descuento' => 0.0,
        ], DocumentoImpresionHn::totales([], 15));
    }

    public function test_porcentaje_impuesto_vacio_usa_el_iva_de_la_empresa(): void
    {
        $detalles = [
            (object) [
                'tipo_gravado' => 'gravada',
                'porcentaje_impuesto' => '',
                'gravada' => 100,
                'iva' => 15,
            ],
            (object) [
                'tipo_gravado' => 'gravada',
                'porcentaje_impuesto' => null,
                'gravada' => 40,
                'iva' => 6,
            ],
        ];

        $totales = DocumentoImpresionHn::totales($detalles, 15);

        $this->assertSame(140.0, $totales['gravado_15']);
        $this->assertSame(21.0, $totales['isv_15']);
        $this->assertSame(0.0, $totales['exento']);
    }

    public function test_porcentaje_impuesto_cero_explicito_sigue_siendo_exento(): void
    {
        $totales = DocumentoImpresionHn::totales([
            (object) [
                'tipo_gravado' => 'gravada',
                'porcentaje_impuesto' => 0,
                'gravada' => 90,
            ],
        ], 15);

        $this->assertSame(90.0, $totales['exento']);
        $this->assertSame(0.0, $totales['gravado_15']);
    }

    public function test_no_sujeta_suma_a_exonerado_con_base_de_respaldo(): void
    {
        $detalles = [
            (object) [
                'tipo_gravado' => 'no_sujeta',
                'gravada' => 0,
                'no_sujeta' => 40,
            ],
            (object) [
                'tipo_gravado' => 'no_sujeta',
                'gravada' => 0,
                'sub_total' => 60,
            ],
            (object) [
                'tipo_gravado' => 'exonerada',
                'gravada' => 0,
                'total' => 25,
            ],
        ];

        $totales = DocumentoImpresionHn::totales($detalles, 15);

        $this->assertSame(125.0, $totales['exonerado']);
        $this->assertSame(0.0, $totales['gravado_15']);
        $this->assertSame(0.0, $totales['exento']);
    }

    public function test_footer_vacio_devuelve_nulos_y_formatea_la_fecha_limite(): void
    {
        $vacio = DocumentoImpresionHn::footer(new Documento([
            'nota' => '   ',
            'resolucion' => '',
            'rangos' => null,
            'fecha' => null,
        ]));

        $this->assertSame([
            'nota' => null,
            'cai' => null,
            'rango' => null,
            'fecha_limite' => null,
        ], $vacio);

        $completo = DocumentoImpresionHn::footer(new Documento([
            'nota' => "Línea 1\nLínea 2",
            'resolucion' => 'CAI-123',
            'rangos' => '001-001-01-00000001 A 001-001-01-00003000',
            'fecha' => '2027-05-23',
        ]));

        $this->assertSame("Línea 1\nLínea 2", $completo['nota']);
        $this->assertSame('001-001-01-00000001 A 001-001-01-00003000', $completo['rango']);
        $this->assertSame('23/05/2027', $completo['fecha_limite']);

        $desdeAutorizacion = DocumentoImpresionHn::footer(new Documento([
            'rangos' => '',
            'numero_autorizacion' => 'Desde 002-001-01-00011001 Hasta 002-001-01-00011500',
            'resolucion' => 'CAI-XYZ',
        ]));
        $this->assertSame('Desde 002-001-01-00011001 Hasta 002-001-01-00011500', $desdeAutorizacion['rango']);
        $this->assertSame('CAI-XYZ', $desdeAutorizacion['cai']);
    }

    public function test_correlativo_sin_numero_emision_conserva_el_valor_plano(): void
    {
        $this->assertSame('439', DocumentoImpresionHn::correlativo(new Documento(), 439));
        $this->assertSame('439', DocumentoImpresionHn::correlativo(new Documento(['numero_emision' => '']), '439'));
    }
}
