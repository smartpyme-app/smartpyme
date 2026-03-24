<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Devoluciones\Detalle as DetalleDevolucion;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Mapea una {@link Venta} al arreglo esperado por dazza-dev/dgt-xml-generator (Factura 01).
 *
 * Reutiliza campos de empresa ya usados para FE (y formularios): cod_actividad_economica, giro,
 * cod_departamento / cod_municipio / cod_distrito (ubicación FE según país), cod_estable, cod_estable_mh,
 * mh_*. CABYS por línea: producto.codigo (13 dígitos), o columna codigo_cabys si existe en BD legada, o custom_empresa.facturacion_fe.cabys_default.
 * Actividad receptor opcional: custom_empresa.facturacion_fe.receptor_actividad_codigo (6 dígitos).
 */
final class CostaRicaInvoiceFromVentaMapper
{
    public function __construct(
        private readonly CostaRicaTipoCambioService $tipoCambio,
    ) {}

    public function buildDocumentData(Venta $venta, Empresa $empresa, int $secuencialFactura): array
    {
        $venta->loadMissing(['detalles.producto', 'cliente']);

        if ($venta->detalles->isEmpty()) {
            throw new InvalidArgumentException('La venta no tiene líneas de detalle.');
        }

        $fecha = Carbon::parse($venta->fecha)->timezone('America/Costa_Rica');
        $dateIso = $fecha->format('Y-m-d\TH:i:sP');

        $est = $this->codigoEstablecimiento3($empresa);
        $ter = $this->codigoTerminal5($empresa);
        $seq = str_pad((string) $secuencialFactura, 10, '0', STR_PAD_LEFT);

        $moneda = strtoupper((string) ($empresa->moneda ?? 'CRC')) === 'USD' ? 'USD' : 'CRC';
        $tipoCambio = $moneda === 'USD' ? $this->tipoCambio->crcPorUsdVenta($empresa) : 1.0;

        return [
            'date' => $dateIso,
            'establishment' => $est,
            'emission_point' => $ter,
            'sequential' => $seq,
            'security_key' => $this->claveSeguridad8(),
            'situation' => 1,
            'sale_condition' => $this->condicionVenta($venta),
            'currency' => [
                'currency_code' => $moneda,
                'exchange_rate' => round($tipoCambio, 5),
            ],
            'issuer' => $this->emisor($empresa),
            'receiver' => $this->receptor($venta, $empresa),
            'line_items' => $venta->detalles->map(fn (Detalle $d) => $this->linea($d, $empresa))->all(),
            'payments' => $this->pagos($venta),
            'summary' => $this->resumen($venta),
        ];
    }

    /**
     * Tiquete electrónico (04): mismos totales que factura; receptor siempre genérico (consumidor final).
     */
    public function buildTicketDocumentData(Venta $venta, Empresa $empresa, int $secuencial): array
    {
        $data = $this->buildDocumentData($venta, $empresa, $secuencial);
        $data['receiver'] = $this->receptorGenerico($empresa);

        return $data;
    }

    public function emisorDatos(Empresa $empresa): array
    {
        return $this->emisor($empresa);
    }

    public function receptorDatosVenta(Venta $venta, Empresa $empresa): array
    {
        return $this->receptor($venta, $empresa);
    }

    public function receptorGenericoDatos(Empresa $empresa, string $nombre = 'Cliente general'): array
    {
        return $this->receptorGenerico($empresa, $nombre);
    }

    /**
     * Encabezado común (fecha de un registro de venta/devolución).
     */
    public function encabezadoDocumento(Empresa $empresa, string $fechaIsoAmericaCr, int $secuencial, string $saleCondition = '01'): array
    {
        $fecha = Carbon::parse($fechaIsoAmericaCr)->timezone('America/Costa_Rica');
        $dateIso = $fecha->format('Y-m-d\TH:i:sP');
        $est = $this->codigoEstablecimiento3($empresa);
        $ter = $this->codigoTerminal5($empresa);
        $seq = str_pad((string) $secuencial, 10, '0', STR_PAD_LEFT);
        $moneda = strtoupper((string) ($empresa->moneda ?? 'CRC')) === 'USD' ? 'USD' : 'CRC';
        $tipoCambio = $moneda === 'USD' ? $this->tipoCambio->crcPorUsdVenta($empresa) : 1.0;

        return [
            'date' => $dateIso,
            'establishment' => $est,
            'emission_point' => $ter,
            'sequential' => $seq,
            'security_key' => $this->claveSeguridad8(),
            'situation' => 1,
            'sale_condition' => $saleCondition,
            'currency' => [
                'currency_code' => $moneda,
                'exchange_rate' => round($tipoCambio, 5),
            ],
        ];
    }

    public function lineaDesdeDetalleDevolucion(DetalleDevolucion $detalle, Empresa $empresa, float $porcentajeIvaEstimado): array
    {
        $detalle->loadMissing('producto');
        $producto = $detalle->producto;
        $cabys = $this->resolverCabysLinea($producto, $empresa);
        if (strlen($cabys) !== 13) {
            throw new InvalidArgumentException(
                'Código CABYS inválido o faltante en línea de devolución. Revise producto o cabys_default en configuración.'
            );
        }

        $cantidad = (float) $detalle->cantidad;
        if ($cantidad <= 0) {
            $cantidad = 1.0;
        }

        $subTotal = round((float) $detalle->precio * $cantidad, 5);
        $totalLinea = round((float) $detalle->total, 5);
        $ivaMonto = round(max(0, $totalLinea - $subTotal), 5);
        if ($ivaMonto < 0.00001 && $porcentajeIvaEstimado > 0) {
            $ivaMonto = round($subTotal * ($porcentajeIvaEstimado / 100), 5);
            $totalLinea = round($subTotal + $ivaMonto, 5);
        }

        [$ivaTarifaCode, $ivaNombre, $rate] = $this->tarifaIva($porcentajeIvaEstimado, $ivaMonto > 0.00001);

        $tipoStr = strtolower((string) ($producto->tipo ?? ''));
        $tipoTx = str_contains($tipoStr, 'servic') ? '05' : '01';
        $desc = $detalle->descripcion ?: ($producto->nombre ?? 'Ítem');

        $line = [
            'cabys_code' => $cabys,
            'description' => mb_substr(strip_tags((string) $desc), 0, 200),
            'quantity' => $cantidad,
            'unit_measure' => $tipoTx === '05' ? 'Sp' : 'Unid',
            'unit_price' => round($subTotal / $cantidad, 5),
            'sub_total' => $subTotal,
            'total_amount' => $subTotal,
            'taxable_base' => $subTotal,
            'transaction_type' => $tipoTx,
            'total_tax' => $ivaMonto,
            'total' => $totalLinea,
        ];

        if ($ivaMonto > 0.00001 && $rate > 0) {
            $line['taxes'] = [[
                'tax_type' => '01',
                'iva_type' => ['code' => $ivaTarifaCode, 'name' => $ivaNombre],
                'rate' => $rate,
                'amount' => $ivaMonto,
            ]];
        }

        return $line;
    }

    public function resumenDesdeDevolucion(Devolucion $devolucion): array
    {
        $grav = round((float) ($devolucion->sub_total ?? 0), 2);
        $exe = round((float) ($devolucion->exenta ?? 0), 2);
        $ns = round((float) ($devolucion->no_sujeta ?? 0), 2);
        $iva = round((float) ($devolucion->iva ?? 0), 2);
        $total = round((float) ($devolucion->total ?? 0), 2);

        $summary = [
            'total_taxed_goods' => $grav,
            'total_exempt_goods' => $exe,
            'total_non_taxable_goods' => $ns,
            'total_taxed_services' => 0.0,
            'total_exempt_services' => 0.0,
            'total_non_taxable_services' => 0.0,
            'total_taxed' => $grav,
            'total_exempt' => $exe,
            'total_non_taxable' => $ns,
            'total_sale' => $grav + $exe + $ns,
            'total_discounts' => 0.0,
            'total_net_sale' => $grav + $exe + $ns,
            'total_tax' => $iva,
            'total' => $total,
        ];

        if ($iva > 0) {
            $summary['taxes'] = [[
                'tax_type' => '01',
                'iva_type' => ['code' => '08', 'name' => 'Tarifa general 13%'],
                'rate' => 13.0,
                'amount' => $iva,
            ]];
        }

        return $summary;
    }

    public function pagosDesdeMonto(float $total): array
    {
        return [[
            'payment_method' => '01',
            'amount' => round($total, 2),
        ]];
    }

    private function codigoEstablecimiento3(Empresa $empresa): string
    {
        $digits = preg_replace('/\D/', '', (string) ($empresa->cod_estable ?? ''));

        return str_pad(substr($digits !== '' ? $digits : '1', 0, 3), 3, '0', STR_PAD_LEFT);
    }

    private function codigoTerminal5(Empresa $empresa): string
    {
        $digits = preg_replace('/\D/', '', (string) ($empresa->cod_estable_mh ?? ''));
        if ($digits === '') {
            return '00001';
        }

        return str_pad(substr($digits, -5), 5, '0', STR_PAD_LEFT);
    }

    private function claveSeguridad8(): string
    {
        return str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    private function condicionVenta(Venta $venta): string
    {
        $c = strtolower(trim((string) ($venta->condicion ?? 'contado')));

        return str_contains($c, 'cred') ? '02' : '01';
    }

    private function ubicacionEmisor(Empresa $empresa): array
    {
        $provDigits = preg_replace('/\D/', '', (string) ($empresa->cod_departamento ?? '1'));
        $prov = $provDigits !== '' ? (int) substr($provDigits, 0, 1) : 1;

        $canDigits = preg_replace('/\D/', '', (string) ($empresa->cod_municipio ?? ''));
        $can = $canDigits !== ''
            ? substr(str_pad($canDigits, 3, '0', STR_PAD_LEFT), 0, 3)
            : '101';

        $disDigits = preg_replace('/\D/', '', (string) ($empresa->cod_distrito ?? ''));
        $dis = $disDigits !== ''
            ? substr(str_pad($disDigits, 5, '0', STR_PAD_LEFT), 0, 5)
            : '10101';

        return ['province' => $prov, 'canton' => $can, 'district' => $dis];
    }

    private function emisor(Empresa $empresa): array
    {
        $codAct = preg_replace('/\D/', '', (string) ($empresa->cod_actividad_economica ?? ''));
        if (strlen($codAct) < 6) {
            throw new InvalidArgumentException(
                'Configure cod_actividad_economica (6 dígitos) según actividad económica registrada en Hacienda CR.'
            );
        }
        $codAct = substr(str_pad($codAct, 6, '0', STR_PAD_LEFT), 0, 6);

        $nit = $this->soloDigitos((string) ($empresa->nit ?? ''));
        if (strlen($nit) < 9) {
            throw new InvalidArgumentException('El NIT/cédula jurídica del emisor no es válido para Costa Rica.');
        }

        $loc = $this->ubicacionEmisor($empresa);

        return [
            'identification_type' => '02',
            'identification_number' => $nit,
            'name' => $empresa->nombre ?? 'Emisor',
            'trade_name' => $empresa->nombre ?? null,
            'activity' => [
                'code' => $codAct,
                'name' => trim((string) ($empresa->giro ?? '')) ?: ($empresa->nombre ?? 'Actividad económica'),
            ],
            'location' => [
                'province' => $loc['province'],
                'canton' => $loc['canton'],
                'district' => $loc['district'],
                'address_details' => $empresa->direccion ?? 'Costa Rica',
            ],
            'phone' => $this->telefonoCr($empresa->telefono ?? '22222222'),
            'email' => array_filter([$empresa->correo ?? null]),
        ];
    }

    private function receptor(Venta $venta, Empresa $empresa): array
    {
        $cliente = $venta->cliente;
        if (! $cliente instanceof Cliente) {
            return $this->receptorGenerico($empresa);
        }

        $nit = $this->soloDigitos((string) ($cliente->nit ?? ''));
        $dui = $this->soloDigitos((string) ($cliente->dui ?? ''));

        if (strlen($nit) >= 9) {
            $tipo = '02';
            $num = $nit;
            $nombre = $cliente->nombre_empresa ?: trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        } elseif (strlen($dui) >= 9) {
            $tipo = '01';
            $num = substr(str_pad($dui, 9, '0', STR_PAD_LEFT), 0, 9);
            $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        } else {
            return $this->receptorGenerico($empresa, trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? '')) ?: 'Cliente');
        }

        $loc = $this->ubicacionEmisor($empresa);

        $receiver = [
            'identification_type' => $tipo,
            'identification_number' => $num,
            'name' => $nombre ?: 'Receptor',
            'location' => [
                'province' => $loc['province'],
                'canton' => $loc['canton'],
                'district' => $loc['district'],
                'address_details' => $cliente->direccion ?: ($empresa->direccion ?? 'Costa Rica'),
            ],
        ];

        $ractCode = $empresa->getCustomConfigValue('facturacion_fe', 'receptor_actividad_codigo', null);
        if ($ractCode) {
            $ract = substr(str_pad(preg_replace('/\D/', '', (string) $ractCode), 6, '0', STR_PAD_LEFT), 0, 6);
            if (strlen($ract) === 6) {
                $receiver['activity'] = ['code' => $ract, 'name' => 'Actividad receptor'];
            }
        }

        if ($cliente->correo) {
            $receiver['email'] = [$cliente->correo];
        }
        if ($cliente->telefono) {
            $receiver['phone'] = $this->telefonoCr($cliente->telefono);
        }

        return $receiver;
    }

    private function receptorGenerico(Empresa $empresa, string $nombre = 'Cliente general'): array
    {
        $loc = $this->ubicacionEmisor($empresa);

        return [
            'identification_type' => '06',
            'identification_number' => '00000000000000',
            'name' => $nombre,
            'location' => [
                'province' => $loc['province'],
                'canton' => $loc['canton'],
                'district' => $loc['district'],
                'address_details' => $empresa->direccion ?? 'Costa Rica',
            ],
        ];
    }

    private function cabysPorDefecto(Empresa $empresa): string
    {
        $raw = $empresa->getCustomConfigValue('facturacion_fe', 'cabys_default', null);

        return preg_replace('/\D/', '', (string) $raw) ?? '';
    }

    private function resolverCabysLinea(?Producto $producto, Empresa $empresa): string
    {
        if ($producto) {
            $candidatos = [];
            $attrs = $producto->getAttributes();
            if (array_key_exists('codigo_cabys', $attrs) && $attrs['codigo_cabys'] !== null && $attrs['codigo_cabys'] !== '') {
                $candidatos[] = $attrs['codigo_cabys'];
            }
            if (! empty($producto->codigo)) {
                $candidatos[] = $producto->codigo;
            }
            foreach ($candidatos as $raw) {
                $d = preg_replace('/\D/', '', (string) $raw);
                if (strlen($d) === 13) {
                    return $d;
                }
            }
        }

        return $this->cabysPorDefecto($empresa);
    }

    private function linea(Detalle $detalle, Empresa $empresa): array
    {
        $producto = $detalle->producto;
        $cabys = $this->resolverCabysLinea($producto, $empresa);
        if (strlen($cabys) !== 13) {
            throw new InvalidArgumentException(
                'Código CABYS inválido o faltante. Use producto.codigo con 13 dígitos (CABYS), o custom_empresa.facturacion_fe.cabys_default.'
            );
        }

        $cantidad = (float) $detalle->cantidad;
        if ($cantidad <= 0) {
            $cantidad = 1.0;
        }

        $subTotal = round((float) $detalle->sub_total, 5);
        $ivaMonto = round((float) ($detalle->iva ?? 0), 5);
        $totalLinea = round((float) $detalle->total, 5);

        $pct = (float) ($detalle->porcentaje_impuesto ?? 0);
        [$ivaTarifaCode, $ivaNombre, $rate] = $this->tarifaIva($pct, $ivaMonto > 0);

        $tipoStr = strtolower((string) ($producto->tipo ?? ''));
        $tipoTx = str_contains($tipoStr, 'servic') ? '05' : '01';

        $line = [
            'cabys_code' => $cabys,
            'description' => mb_substr(strip_tags((string) $detalle->descripcion), 0, 200),
            'quantity' => $cantidad,
            'unit_measure' => $tipoTx === '05' ? 'Sp' : 'Unid',
            'unit_price' => round($subTotal / $cantidad, 5),
            'sub_total' => $subTotal,
            'total_amount' => $subTotal,
            'taxable_base' => $subTotal,
            'transaction_type' => $tipoTx,
            'total_tax' => $ivaMonto,
            'total' => $totalLinea,
        ];

        if ($ivaMonto > 0 && $rate > 0) {
            $line['taxes'] = [[
                'tax_type' => '01',
                'iva_type' => ['code' => $ivaTarifaCode, 'name' => $ivaNombre],
                'rate' => $rate,
                'amount' => $ivaMonto,
            ]];
        }

        return $line;
    }

    /**
     * @return array{0: string, 1: string, 2: float}
     */
    private function tarifaIva(float $porcentajeDeclarado, bool $tieneIva): array
    {
        if (! $tieneIva || $porcentajeDeclarado <= 0) {
            return ['10', 'Tarifa Exenta', 0.0];
        }

        if (abs($porcentajeDeclarado - 13) < 0.01) {
            return ['08', 'Tarifa general 13%', 13.0];
        }

        if (abs($porcentajeDeclarado - 4) < 0.01) {
            return ['04', 'Tarifa reducida 4%', 4.0];
        }

        if (abs($porcentajeDeclarado - 2) < 0.01) {
            return ['03', 'Tarifa reducida 2%', 2.0];
        }

        return ['08', 'Tarifa general 13%', 13.0];
    }

    private function pagos(Venta $venta): array
    {
        return [[
            'payment_method' => '01',
            'amount' => round((float) $venta->total, 2),
        ]];
    }

    private function resumen(Venta $venta): array
    {
        $grav = round((float) ($venta->gravada ?? 0), 2);
        $exe = round((float) ($venta->exenta ?? 0), 2);
        $ns = round((float) ($venta->no_sujeta ?? 0), 2);
        $iva = round((float) ($venta->iva ?? 0), 2);
        $desc = round((float) ($venta->descuento ?? 0), 2);
        $sub = round((float) ($venta->sub_total ?? 0), 2);
        $total = round((float) ($venta->total ?? 0), 2);

        $summary = [
            'total_taxed_goods' => $grav,
            'total_exempt_goods' => $exe,
            'total_non_taxable_goods' => $ns,
            'total_taxed_services' => 0.0,
            'total_exempt_services' => 0.0,
            'total_non_taxable_services' => 0.0,
            'total_taxed' => $grav,
            'total_exempt' => $exe,
            'total_non_taxable' => $ns,
            'total_sale' => $sub + $desc,
            'total_discounts' => $desc,
            'total_net_sale' => $sub,
            'total_tax' => $iva,
            'total' => $total,
        ];

        if ($iva > 0) {
            $summary['taxes'] = [[
                'tax_type' => '01',
                'iva_type' => ['code' => '08', 'name' => 'Tarifa general 13%'],
                'rate' => 13.0,
                'amount' => $iva,
            ]];
        }

        return $summary;
    }

    private function telefonoCr(string $raw): array
    {
        $n = preg_replace('/\D/', '', $raw);

        return [
            'country_code' => '506',
            'number' => substr($n, -8) ?: '00000000',
        ];
    }

    private function soloDigitos(string $s): string
    {
        return preg_replace('/\D/', '', $s) ?? '';
    }
}
