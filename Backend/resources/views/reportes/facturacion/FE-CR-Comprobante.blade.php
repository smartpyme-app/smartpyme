<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $clave ?: 'Comprobante electrónico CR' }}</title>
    <style>
        * { margin: 0; font-family: "DejaVu Sans", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        body { margin: 36px 40px 48px; font-size: 9px; color: #1a1a1a; }
        h1, h2, h3 { color: #003366 !important; }
        h2.doc-title { font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px; }
        h3.doc-sub { font-size: 12px; margin: 0; font-weight: 700; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td {
            border-collapse: collapse;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }
        .table.bordered th, .table.bordered td { border: 1px solid #999; }
        .table.bordered { page-break-inside: auto; }
        .table.bordered tr { page-break-inside: avoid; }
        .table.bordered thead { display: table-header-group; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .bg-light { background-color: #e8e8e8; }
        .muted { color: #555; font-size: 8px; }
        .legal { font-size: 7.5px; color: #444; line-height: 1.35; margin-top: 14px; padding-top: 10px; border-top: 1px solid #bbb; }
        p { margin: 2px 0; }
        .mb-2 { margin-bottom: 6px; }
        .row-subtotal td { font-weight: 700; background-color: #f0f0f0; }
        .clave-bloque { font-size: 8px; letter-spacing: 0.3px; line-height: 1.35; word-break: break-all; }
        .totales-ext td { padding: 3px 6px; }
        .monto-letras { font-size: 8px; margin-top: 8px; font-style: italic; text-transform: uppercase; }
    </style>
</head>
<body>
@php
    $iss = $documento['issuer'] ?? [];
    $rec = $documento['receiver'] ?? [];
    $lines = $documento['line_items'] ?? [];
    if (! is_array($lines)) { $lines = []; }
    $sum = $documento['summary'] ?? [];
    if (! is_array($sum)) { $sum = []; }
    $payments = $documento['payments'] ?? [];
    if (! is_array($payments)) { $payments = []; }
    $currency = $documento['currency'] ?? [];
    $monedaCod = strtoupper((string) ($currency['currency_code'] ?? 'CRC'));
    $simbolo = $monedaCod === 'USD' ? 'USD' : 'CRC';
    $fecha = $documento['date'] ?? '';
    $est = (string) ($documento['establishment'] ?? '');
    $punto = (string) ($documento['emission_point'] ?? '');
    $seq = (string) ($documento['sequential'] ?? '');
    $consecutivoParts = array_filter([$est, $punto, $seq], static fn ($x) => $x !== '' && $x !== null);
    $consecutivo = implode('-', $consecutivoParts);
    $saleCond = str_pad((string) ($documento['sale_condition'] ?? '01'), 2, '0', STR_PAD_LEFT);
    $saleCondOtro = trim((string) ($documento['sale_condition_other'] ?? $documento['other_sale_condition'] ?? ''));
    $condicionMap = [
        '01' => 'Contado', '02' => 'Crédito', '03' => 'Consignación', '04' => 'Apartado',
        '05' => 'Arrendamiento con opción de compra', '06' => 'Arrendamiento en función financiera',
        '07' => 'Cobro a favor de un tercero', '08' => 'Servicios prestados al Estado',
        '09' => 'Pago de servicios prestado al Estado', '10' => 'Venta a crédito en IVA hasta 90 días',
        '11' => 'Pago de venta a crédito en IVA hasta 90 días', '12' => 'Venta mercancía no nacionalizada',
        '13' => 'Venta bienes usados no contribuyente', '14' => 'Arrendamiento operativo', '15' => 'Arrendamiento financiero',
    ];
    if ($saleCond === '99' && $saleCondOtro !== '') {
        $condicionTxt = $saleCondOtro;
    } elseif (isset($condicionMap[$saleCond])) {
        $condicionTxt = $condicionMap[$saleCond];
    } else {
        $condicionTxt = $saleCond !== '' ? ('Código '.$saleCond.($saleCondOtro !== '' ? ' · '.$saleCondOtro : '')) : '—';
    }
    $tipoDocCodigo = $tipoDteCodigo ?? '01';
    $tiposNombre = [
        '01' => 'Factura electrónica',
        '02' => 'Nota de débito electrónica',
        '03' => 'Nota de crédito electrónica',
        '04' => 'Tiquete electrónico',
        '08' => 'Factura electrónica de compra',
        '11' => 'Factura electrónica de exportación',
    ];
    $nombreTipoNormativo = $tiposNombre[$tipoDocCodigo] ?? ($titulo ?? 'Comprobante electrónico');
    $claveSoloDigitos = preg_replace('/\D/', '', (string) $clave);
    // Hacienda CR: la clave de 50 dígitos es el dato de consulta pública y del código QR (Anexos técnicos DGT).
    $qrPayload = strlen($claveSoloDigitos) >= 40 ? $claveSoloDigitos : 'https://atv.hacienda.go.cr/ATV/frmConsultaFactura.aspx';
    $empresa = $registro->empresa ?? null;
    $logoFile = $empresa && ! empty($empresa->logo) ? $empresa->logo : null;
    $logoAbs = $logoFile ? public_path('img/'.$logoFile) : null;
    $logoSrc = ($logoAbs && is_readable($logoAbs)) ? str_replace('\\', '/', $logoAbs) : null;
    $feAmbiente = $empresa->fe_ambiente ?? null;
    $leyendaAmbiente = $feAmbiente === '00' ? 'Pruebas (sin validez tributaria)' : ($feAmbiente === '01' ? 'Producción' : '');

    $tipoId = static function (?string $c): string {
        $c = str_pad((string) $c, 2, '0', STR_PAD_LEFT);
        $m = [
            '01' => 'Cédula física',
            '02' => 'Cédula jurídica',
            '03' => 'DIMEX',
            '04' => 'NITE',
            '05' => 'Extranjero no domiciliado',
            '06' => 'No contribuyente / uso general',
        ];

        return $m[$c] ?? ('Tipo '.$c);
    };

    $medioPago = static function (?string $c, ?string $otro = null): string {
        $c = str_pad((string) $c, 2, '0', STR_PAD_LEFT);
        $m = [
            '01' => 'Efectivo',
            '02' => 'Tarjeta',
            '03' => 'Cheque',
            '04' => 'Transferencia o depósito bancario',
            '05' => 'Recaudado por terceros',
            '06' => 'SINPE Móvil',
            '07' => 'Plataforma digital',
            '99' => 'Otros',
        ];
        if ($c === '99' && $otro !== null && trim($otro) !== '') {
            return 'Otros: '.$otro;
        }

        return $m[$c] ?? ('Código '.$c);
    };

    $fmtTel = static function ($p): string {
        if (! is_array($p)) {
            return '';
        }
        $cc = $p['country_code'] ?? '';
        $n = $p['number'] ?? '';

        return trim(($cc ? '+'.$cc.' ' : '').$n);
    };

    $fmtUbicacion = static function ($entity): string {
        $loc = is_array($entity) ? ($entity['location'] ?? null) : null;
        if (! is_array($loc)) {
            return '';
        }
        $parts = [];
        if (! empty($loc['address_details'])) {
            $parts[] = $loc['address_details'];
        }
        $prov = $loc['province'] ?? null;
        $can = $loc['canton'] ?? null;
        $dis = $loc['district'] ?? null;
        if (is_array($prov) && isset($prov['code'])) {
            $parts[] = 'Prov. '.$prov['code'];
        } elseif ($prov !== null && $prov !== '') {
            $parts[] = 'Prov. '.$prov;
        }
        if ($can !== null && $can !== '') {
            $parts[] = 'Cantón '.$can;
        }
        if ($dis !== null && $dis !== '') {
            $parts[] = 'Distrito '.$dis;
        }
        $bar = $loc['neighborhood'] ?? null;
        if ($bar !== null && $bar !== '') {
            $parts[] = 'Barrio '.$bar;
        }

        return implode(', ', array_filter($parts));
    };

    $emailsLine = static function ($entity): string {
        $e = $entity['email'] ?? null;
        if (is_array($e)) {
            return implode(', ', array_filter($e));
        }

        return is_string($e) ? $e : '';
    };

    $fechaFmt = '—';
    if ($fecha !== '') {
        try {
            $fechaFmt = \Carbon\Carbon::parse($fecha)->timezone('America/Costa_Rica')->format('d/m/Y H:i:s');
        } catch (\Throwable $e) {
            $fechaFmt = $fecha;
        }
    }
    $feCrPdf = $feCrPdf ?? \App\Support\FacturacionElectronica\CostaRicaFeComprobantePdfAggregates::fromDocument(
        is_array($documento) ? $documento : [],
        (string) ($clave ?? ''),
        $monedaCod
    );
    $claveFmt = $feCrPdf['clave_formateada'] ?? (string) $clave;
    $fmtUm = static function ($u): string {
        if (is_array($u)) {
            return (string) ($u['code'] ?? $u['name'] ?? '');
        }

        return (string) $u;
    };
    $pctImpLinea = static function (array $line): string {
        $taxes = $line['taxes'] ?? [];
        if (! is_array($taxes) || ! isset($taxes[0]) || ! is_array($taxes[0])) {
            return '—';
        }
        $r = (float) ($taxes[0]['rate'] ?? 0);
        $tax = (float) ($line['total_tax'] ?? 0);
        $sub = (float) ($line['sub_total'] ?? 0);
        if ($tax > 1e-5 && $sub > 1e-5 && $r < 1e-5) {
            $r = round(100.0 * $tax / $sub, 2);
        }
        if ($tax < 1e-5) {
            return '0';
        }

        return number_format($r, 2, '.', '');
    };
@endphp

    <div class="dte-header mb-2">
        <table class="table">
            <tbody>
                <tr>
                    <td style="width: 22%;">
                        @if ($logoSrc)
                            <img height="100" src="{{ $logoSrc }}" alt="Logo" style="max-width: 140px;">
                        @endif
                    </td>
                    <td style="width: 56%; text-align: center;">
                        <h2 class="doc-title">Ministerio de Hacienda · República de Costa Rica</h2>
                        <h3 class="doc-sub">Documento tributario electrónico</h3>
                        <p style="margin-top:6px;font-size:11px;font-weight:bold;">{{ $nombreTipoNormativo }}</p>
                        <p class="muted">Tipo comprobante (código): {{ $tipoDocCodigo }}</p>
                    </td>
                    <td style="width: 22%; text-align: right;">
                        @if(strlen((string) $qrPayload) > 0)
                            {!! '<img width="115" height="115" style="display:inline-block;" src="data:image/png;base64,' . DNS2D::getBarcodePNG($qrPayload, 'QRCODE', 8, 1, [0,0,0], true) . '" alt="Código QR" />' !!}
                            <p class="muted" style="margin-top:4px;max-width:115px;margin-left:auto;">Consulta: clave o enlace ATV</p>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <table class="table bordered" style="margin-top:8px;">
            <tbody>
                <tr>
                    <td colspan="2" class="bg-light" style="padding:8px;">
                        <p style="margin:0 0 4px;"><b>Tipo de comprobante:</b> {{ $nombreTipoNormativo }} <span class="muted">(código {{ $tipoDocCodigo }})</span></p>
                        <p style="margin:0 0 4px;"><b>Clave numérica:</b></p>
                        <p class="clave-bloque" style="margin:0 0 6px;">{{ $claveFmt }}</p>
                        <p style="margin:0;"><b>Consecutivo:</b> {{ $consecutivo !== '' ? $consecutivo : '—' }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <p><b>Fecha y hora de emisión:</b> {{ $fechaFmt }}</p>
                        <p><b>Condición de venta:</b> {{ $condicionTxt }}</p>
                    </td>
                    <td style="width: 50%; vertical-align: top;">
                        <p><b>Moneda / tipo de cambio:</b>
                            {{ $monedaCod }}
                            @if (isset($currency['exchange_rate']) && (float) $currency['exchange_rate'] > 0)
                                @if($monedaCod === 'USD')
                                    · TC {{ number_format((float) $currency['exchange_rate'], 5, '.', '') }} CRC/USD
                                @else
                                    · TC {{ number_format((float) $currency['exchange_rate'], 5, '.', '') }}
                                @endif
                            @endif
                        </p>
                        @if ($leyendaAmbiente !== '')
                            <p><b>Ambiente FE:</b> {{ $leyendaAmbiente }}</p>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <br/>

    <table class="table bordered">
        <tbody>
            <tr>
                <td class="bg-light" style="width: 50%;"><h3 style="font-size:10px;margin:0;">Emisor</h3></td>
                <td class="bg-light" style="width: 50%;"><h3 style="font-size:10px;margin:0;">Receptor</h3></td>
            </tr>
            <tr>
                <td style="width: 50%;">
                    <p><b>Nombre o razón social:</b> {{ $iss['name'] ?? '—' }}</p>
                    @if(!empty($iss['identification_number']))
                        <p><b>Identificación:</b> {{ $tipoId($iss['identification_type'] ?? '') }} · {{ $iss['identification_number'] }}</p>
                    @endif
                    @if(!empty($iss['trade_name']))
                        <p><b>Nombre comercial:</b> {{ $iss['trade_name'] }}</p>
                    @endif
                    @if(!empty($iss['activity']))
                        <p><b>Actividad económica:</b> {{ $iss['activity'] }}</p>
                    @endif
                    <p><b>Ubicación:</b> {{ $fmtUbicacion($iss) ?: '—' }}</p>
                    @php $tEl = $fmtTel($iss['phone'] ?? null); @endphp
                    @if($tEl !== '')
                        <p><b>Teléfono:</b> {{ $tEl }}</p>
                    @endif
                    @php $em = $emailsLine($iss); @endphp
                    @if($em !== '')
                        <p><b>Correo:</b> {{ $em }}</p>
                    @endif
                </td>
                <td style="width: 50%;">
                    <p><b>Nombre o razón social:</b> {{ $rec['name'] ?? '—' }}</p>
                    @if(!empty($rec['identification_number']))
                        <p><b>Identificación:</b> {{ $tipoId($rec['identification_type'] ?? '') }} · {{ $rec['identification_number'] }}</p>
                    @endif
                    @if(!empty($rec['activity']))
                        <p><b>Actividad económica (receptor):</b> {{ $rec['activity'] }}</p>
                    @endif
                    <p><b>Ubicación:</b> {{ $fmtUbicacion($rec) ?: '—' }}</p>
                    @php $tR = $fmtTel($rec['phone'] ?? null); @endphp
                    @if($tR !== '')
                        <p><b>Teléfono:</b> {{ $tR }}</p>
                    @endif
                    @php $er = $emailsLine($rec); @endphp
                    @if($er !== '')
                        <p><b>Correo:</b> {{ $er }}</p>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    @if (!empty($documento['referenced_documents']) && is_array($documento['referenced_documents']))
        <br/>
        <table class="table bordered">
            <thead>
                <tr class="bg-light"><th colspan="4">Documentos de referencia</th></tr>
                <tr>
                    <th>Tipo</th>
                    <th>Clave / número</th>
                    <th>Fecha</th>
                    <th>Razón / código</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documento['referenced_documents'] as $ref)
                    @if (is_array($ref))
                        <tr>
                            <td>{{ $ref['document_type'] ?? '' }}</td>
                            <td>{{ $ref['document_number'] ?? '' }}</td>
                            <td>{{ $ref['emission_date'] ?? '' }}</td>
                            <td>
                                {{ $ref['reason'] ?? '' }}
                                @if (! empty($ref['referenced_code']))
                                    ({{ $ref['referenced_code'] }})
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    <br/>

    <table class="table bordered">
        <thead>
            <tr class="bg-light">
                <th style="width:3%;">N°</th>
                <th style="width:10%;">Código CABYS</th>
                <th>Detalle</th>
                <th style="width:5%;" class="text-right">Cant.</th>
                <th style="width:5%;">Unid.</th>
                <th style="width:8%;" class="text-right">P. unit.</th>
                <th style="width:5%;" class="text-right">% Desc.</th>
                <th style="width:7%;" class="text-right">Desc.</th>
                <th style="width:8%;" class="text-right">Total neto</th>
                <th style="width:5%;" class="text-right">% Imp.</th>
                <th style="width:7%;" class="text-right">Impuesto</th>
                <th style="width:8%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
        @foreach($feCrPdf['line_groups'] ?? [] as $grupo)
            @foreach($grupo['lines'] ?? [] as $entry)
                @if(is_array($entry['line'] ?? null))
                @php $line = $entry['line']; $ivaLinea = isset($line['total_tax']) ? (float) $line['total_tax'] : 0.0; @endphp
                <tr>
                    <td>{{ $entry['idx'] ?? '' }}</td>
                    <td style="font-size:7px;">{{ $line['cabys_code'] ?? '' }}</td>
                    <td>{{ $line['description'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($line['quantity'] ?? 0), 2, '.', '') }}</td>
                    <td style="font-size:7px;">{{ $fmtUm($line['unit_measure'] ?? '') }}</td>
                    <td class="text-right">{{ number_format((float) ($line['unit_price'] ?? 0), 2, '.', '') }}</td>
                    <td class="text-right">{{ isset($line['discount_rate']) ? number_format((float) $line['discount_rate'], 2, '.', '') : '—' }}</td>
                    <td class="text-right">{{ isset($line['discount_amount']) ? number_format((float) $line['discount_amount'], 2, '.', '') : '—' }}</td>
                    <td class="text-right">{{ number_format((float) ($line['sub_total'] ?? 0), 2, '.', '') }}</td>
                    <td class="text-right">{{ $pctImpLinea($line) }}</td>
                    <td class="text-right">{{ number_format($ivaLinea, 2, '.', '') }}</td>
                    <td class="text-right">{{ number_format((float) ($line['total'] ?? 0), 2, '.', '') }}</td>
                </tr>
                @endif
            @endforeach
            <tr class="row-subtotal">
                <td colspan="8" class="text-right">{{ $grupo['subtotal_row_label'] ?? '' }}</td>
                <td class="text-right">{{ number_format((float) ($grupo['sub_net'] ?? 0), 2, '.', '') }}</td>
                <td></td>
                <td class="text-right">{{ number_format((float) ($grupo['sub_tax'] ?? 0), 2, '.', '') }}</td>
                <td class="text-right">{{ number_format((float) ($grupo['sub_total'] ?? 0), 2, '.', '') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <br/>

    <table class="table" style="width:100%;">
        <tbody>
            <tr>
                <td style="width:48%; vertical-align: top; padding-right:8px;">
                    <table class="table bordered">
                        <thead>
                            <tr class="bg-light">
                                <th>Tarifa / concepto</th>
                                <th class="text-right">Base imponible</th>
                                <th class="text-right">Impuesto</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($feCrPdf['tax_table_rows'] ?? [] as $fila)
                            <tr>
                                <td>{{ $fila['label'] ?? '' }}</td>
                                <td class="text-right">{{ number_format((float) ($fila['base'] ?? 0), 2, '.', '') }}</td>
                                <td class="text-right">{{ number_format((float) ($fila['tax'] ?? 0), 2, '.', '') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </td>
                <td style="width:52%; vertical-align: top; padding-left:8px;">
                    <table class="table bordered totales-ext">
                        <tbody>
                            @if(isset($sum['total_exonerated']) && (float) $sum['total_exonerated'] > 0)
                            <tr><td>Total exonerado</td><td class="text-right">{{ number_format((float) $sum['total_exonerated'], 2, '.', '') }}</td></tr>
                            @endif
                            <tr><td>Total gravado</td><td class="text-right">{{ number_format((float) ($sum['total_taxed'] ?? 0), 2, '.', '') }}</td></tr>
                            <tr><td>Total exento</td><td class="text-right">{{ number_format((float) ($sum['total_exempt'] ?? 0), 2, '.', '') }}</td></tr>
                            <tr><td>Total no sujeto / no facturado</td><td class="text-right">{{ number_format((float) ($sum['total_non_taxable'] ?? 0), 2, '.', '') }}</td></tr>
                            <tr><td>Total venta</td><td class="text-right">{{ number_format((float) ($sum['total_sale'] ?? 0), 2, '.', '') }}</td></tr>
                            <tr><td>Total descuentos</td><td class="text-right">{{ number_format((float) ($sum['total_discounts'] ?? 0), 2, '.', '') }}</td></tr>
                            <tr><td>Total venta neta</td><td class="text-right">{{ number_format((float) ($sum['total_net_sale'] ?? 0), 2, '.', '') }}</td></tr>
                            <tr><td>Total impuesto (IVA)</td><td class="text-right">{{ number_format((float) ($sum['total_tax'] ?? 0), 2, '.', '') }}</td></tr>
                            @if(isset($sum['total_iva_devuelto']) && abs((float) $sum['total_iva_devuelto']) > 1e-5)
                            <tr><td>Total IVA devuelto</td><td class="text-right">{{ number_format((float) $sum['total_iva_devuelto'], 2, '.', '') }}</td></tr>
                            @endif
                            <tr class="bg-light"><td><b>Total comprobante</b></td><td class="text-right"><b>{{ number_format((float) ($sum['total'] ?? 0), 2, '.', '') }}</b></td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    @if(!empty($feCrPdf['total_en_letras'] ?? ''))
        <p class="monto-letras"><b>Son:</b> {{ $feCrPdf['total_en_letras'] }}</p>
    @endif

    @if(count($payments) > 0)
        <br/>
        <table class="table bordered">
            <thead>
                <tr class="bg-light"><th colspan="2">Medios de pago</th></tr>
                <tr><th>Medio</th><th class="text-right">Monto</th></tr>
            </thead>
            <tbody>
            @foreach($payments as $p)
                @if(is_array($p))
                <tr>
                    <td>{{ $medioPago($p['payment_method'] ?? '', $p['other_text'] ?? $p['payment_other'] ?? null) }}</td>
                    <td class="text-right">{{ isset($p['amount']) ? number_format((float) $p['amount'], 2, '.', '') : '' }}</td>
                </tr>
                @endif
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="legal">
        <p><b>Transparencia tributaria (Costa Rica):</b> este documento es una <b>representación gráfica</b> del comprobante electrónico
        transmitido al Ministerio de Hacienda (DGT). La validez tributaria corresponde al comprobante aceptado en el sistema de comprobantes electrónicos.</p>
        <p style="margin-top:4px;">La <b>clave numérica</b> de este documento permite identificar el comprobante ante Hacienda. El <b>código QR</b>
        incorpora la clave (o enlace a consulta pública) para verificación. Consulta de comprobantes en el portal del contribuyente:
        <span style="word-break:break-all;">https://atv.hacienda.go.cr/ATV/frmConsultaFactura.aspx</span></p>
        <p style="margin-top:4px;" class="muted">Documento generado desde SmartPyME. Conserve el XML y las respuestas de validación según su política de archivo y criterio contable.</p>
    </div>
</body>
</html>
