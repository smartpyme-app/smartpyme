<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Compras\Gastos\DetalleEgreso;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Proveedores\Proveedor;
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
 * Ubicación emisor/receptor en XML: código distrito CR de 5 dígitos (facturacion_fe.emisor_distrito o cod_distrito si ya es CR).
 * No usar distrito/municipio de El Salvador rellenado a 5 dígitos. Establecimiento (3 dígitos) y terminal / punto de
 * venta (5 dígitos) en la clave DGT: igual que FE de SV, por sucursal de la venta/compra/gasto ({@see Sucursal::cod_estable_mh},
 * {@see Sucursal::codigo_punto_venta}); si no hay sucursal o los campos vienen vacíos: empresa.cod_estable y empresa.cod_estable_mh.
 * mh_*. CABYS por línea: producto.codigo_cabys, producto.codigo (13 dígitos); fallback solo
 * custom_empresa.facturacion_fe.cabys_default (13 dígitos). Actividad del emisor: cod_actividad_economica
 * según Hacienda; se normaliza al formato del catálogo DGT (actividades-economicas.json), p. ej. "7020.0", no "070200".
 * Tipo identificación emisor: facturacion_fe.emisor_tipo_identificacion (01–05, default 02).
 * Actividad del receptor en FEC (08), cabecera XML: obligatoria (XSD); facturacion_fe.receptor_actividad_codigo o proveedor.cod_giro (normalización DGT).
 */
final class CostaRicaInvoiceFromVentaMapper
{
    public function __construct(
        private readonly CostaRicaTipoCambioService $tipoCambio,
    ) {}

    public function buildDocumentData(Venta $venta, Empresa $empresa, int $secuencialFactura): array
    {
        $venta->loadMissing(['detalles.producto', 'cliente', 'sucursal']);

        if ($venta->detalles->isEmpty()) {
            throw new InvalidArgumentException('La venta no tiene líneas de detalle.');
        }

        // Fecha/hora de emisión del XML: instante actual en CR (evita rechazo -53 por desfase con hora oficial DGT).
        $fecha = Carbon::now('America/Costa_Rica');
        $dateIso = $fecha->format('Y-m-d\TH:i:sP');

        [$est, $ter] = $this->establecimientoYTerminalCr($empresa, $venta->sucursal);
        $seq = str_pad((string) $secuencialFactura, 10, '0', STR_PAD_LEFT);

        $moneda = strtoupper((string) ($empresa->moneda ?? 'CRC')) === 'USD' ? 'USD' : 'CRC';
        $tipoCambio = $moneda === 'USD' ? $this->tipoCambio->crcPorUsdVenta($empresa) : 1.0;

        // Índices 0..n-1: el resumen y Hacienda (-111) emparejan líneas con el XML; sin array_values el map puede conservar
        // keys no secuenciales y desalinear servicios gravados vs mercancías gravadas.
        $lineItems = array_values($venta->detalles->map(fn (Detalle $d) => $this->linea($d, $empresa, $venta))->all());

        $payload = [
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
            'line_items' => $lineItems,
            'payments' => $this->pagos($venta),
            'summary' => $this->resumenAlineadoALineas($venta, $lineItems),
        ];

        $metaEx = $this->metadataExoneracionCr($venta);
        if ($metaEx !== null) {
            $payload['fe_cr_exoneracion'] = $metaEx;
        }

        return $payload;
    }

    /**
     * Tiquete electrónico (04): mismos totales que factura; receptor siempre genérico (consumidor final).
     * XSD v4.4: el tiquete no incluye el nodo TipoTransaccion por línea (sí la factura); el XML usa items-ticket.xml.twig.
     *
     * El receptor no puede usar tipo 06 (No contribuyente) como en la factura genérica: la validación DGT (-409) exige
     * para TE consumidor final anónimo el tipo 05 (Extranjero no domiciliado) con número acorde a la nota 4.
     */
    public function buildTicketDocumentData(Venta $venta, Empresa $empresa, int $secuencial): array
    {
        $data = $this->buildDocumentData($venta, $empresa, $secuencial);
        $data['receiver'] = $this->receptorGenericoTiquete($empresa);

        // XSD v4.4 tiquete: LineaDetalle no incluye TipoTransaccion; quitar clave para que Twig/plantilla no emita el nodo.
        $data['line_items'] = array_values(array_map(function (array $line): array {
            unset($line['transaction_type']);

            return $line;
        }, $data['line_items']));

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
     *
     * @param  Sucursal|null  $sucursal  Sucursal de la operación (NC, ND, etc.); determina establecimiento y punto de venta como en FE SV.
     */
    public function encabezadoDocumento(Empresa $empresa, string $fechaIsoAmericaCr, int $secuencial, string $saleCondition = '01', ?Sucursal $sucursal = null): array
    {
        $fecha = Carbon::parse($fechaIsoAmericaCr)->timezone('America/Costa_Rica');
        $dateIso = $fecha->format('Y-m-d\TH:i:sP');
        [$est, $ter] = $this->establecimientoYTerminalCr($empresa, $sucursal);
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
                'Código CABYS inválido o faltante en línea de devolución. Revise CABYS del producto, código 13 dígitos en producto, o cabys_default en facturacion_fe (custom_empresa).'
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

        [$ivaTarifaCode, , $rate] = $this->tarifaIva($porcentajeIvaEstimado, $ivaMonto > 0.00001);

        $desc = $detalle->descripcion ?: ($producto->nombre ?? 'Ítem');

        // Catálogo DGT tipos-transaccion.json: 01 = «Venta normal de bienes y servicios» (general), no significa «solo mercancía».
        // Los códigos 02–05 son autoconsumo u otros; no son «02=servicio». Hacienda cuadra TotalServGravados vs TotalMercanciasGravadas
        // según línea (p. ej. Unid vs Sp), no cambiando 01↔02 en tipo transacción. Mantener 01 en venta corriente.
        $esServicio = $this->esLineaServicioCr($producto);

        $line = [
            'cabys_code' => $cabys,
            'description' => mb_substr(strip_tags((string) $desc), 0, 200),
            'quantity' => $cantidad,
            'unit_measure' => $esServicio ? 'Sp' : 'Unid',
            'unit_price' => round($subTotal / $cantidad, 5),
            'sub_total' => $subTotal,
            'total_amount' => $subTotal,
            'taxable_base' => $subTotal,
            'transaction_type' => '01',
            'total_tax' => $ivaMonto,
            'total' => $totalLinea,
        ];

        // XSD v4.4: antes de <ImpuestoNeto> debe existir al menos un <Impuesto> o <ImpuestoAsumidoEmisorFabrica>.
        $gravado = $ivaMonto > 0.00001 && $rate > 0;
        $line['taxes'] = [[
            'tax_type' => '01',
            'iva_type' => $this->codigoTarifaIvaDosDigitos($ivaTarifaCode),
            'rate' => $gravado ? $rate : 0.0,
            'amount' => $gravado ? $ivaMonto : 0.0,
        ]];

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
                'iva_type' => '08',
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

    /**
     * Código establecimiento (3 dígitos) y terminal / punto de venta (5 dígitos) para XML y clave DGT.
     * Misma fuente que FE de SV: sucursal de la operación; respaldo en empresa.
     *
     * @return array{0: string, 1: string}
     */
    private function establecimientoYTerminalCr(Empresa $empresa, ?Sucursal $sucursal): array
    {
        $rawEst = '';
        $rawTer = '';
        if ($sucursal !== null) {
            $rawEst = trim((string) ($sucursal->cod_estable_mh ?? ''));
            $rawTer = trim((string) ($sucursal->codigo_punto_venta ?? ''));
        }

        if ($rawEst === '') {
            $rawEst = (string) ($empresa->cod_estable ?? '');
        }
        if ($rawTer === '') {
            $rawTer = (string) ($empresa->cod_estable_mh ?? '');
        }

        return [$this->normalizarCodigoEstablecimiento3($rawEst), $this->normalizarTerminal5($rawTer)];
    }

    private function normalizarCodigoEstablecimiento3(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            $digits = '1';
        }

        return str_pad(substr($digits, 0, 3), 3, '0', STR_PAD_LEFT);
    }

    private function normalizarTerminal5(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
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

    /**
     * Catálogo tipo identificación emisor (Hacienda CR / DGT). 06 = no contribuyente (no aplica a emisor).
     */
    private function tipoIdentificacionEmisorCr(Empresa $empresa): string
    {
        $allowed = ['01', '02', '03', '04', '05'];
        $raw = trim((string) $empresa->getCustomConfigValue('facturacion_fe', 'emisor_tipo_identificacion', '02'));
        if (! in_array($raw, $allowed, true)) {
            return '02';
        }

        return $raw;
    }

    /**
     * distritos.json (DGT): claves de 5 dígitos; el primero es provincia (1–7). canton = primeros 3 dígitos.
     * facturacion_fe.emisor_distrito tiene prioridad; cod_distrito solo si ya es un código CR válido (no rellenar con ceros códigos de SV).
     */
    private function resolverDistritoCostaRicaCincoDigitos(Empresa $empresa): string
    {
        $prioridad = [
            $empresa->getCustomConfigValue('facturacion_fe', 'emisor_distrito', null),
            $empresa->cod_distrito ?? null,
        ];

        foreach ($prioridad as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $d = preg_replace('/\D/', '', (string) $raw);
            if ($d === '') {
                continue;
            }
            if (strlen($d) > 5) {
                $d = substr($d, 0, 5);
            }
            if (strlen($d) === 5 && preg_match('/^[1-7]\d{4}$/', $d) === 1) {
                return $d;
            }
        }

        throw new InvalidArgumentException(
            'Configure el distrito fiscal de Costa Rica para el XML (código de 5 dígitos del catálogo INEC/DGT, ej. 10101). '
            .'En la empresa: facturacion_fe.emisor_distrito, o cod_distrito solo si ya guarda ese código de 5 dígitos. '
            .'Los códigos de distrito de El Salvador no aplican: al rellenarlos con ceros se generan valores inválidos (ej. 00014).'
        );
    }

    private function ubicacionEmisor(Empresa $empresa): array
    {
        $dis = $this->resolverDistritoCostaRicaCincoDigitos($empresa);

        return [
            'province' => (int) $dis[0],
            'canton' => substr($dis, 0, 3),
            'district' => $dis,
        ];
    }

    /**
     * XSD Hacienda (BarrioUbicacionType): minLength 5. La plantilla dgt-xml-generator siempre emite el elemento Barrio
     * con el texto de neighborhood; si falta, el XML queda vacío y falla la validación.
     */
    private function textoBarrioUbicacionXml(string ...$candidatos): string
    {
        foreach ($candidatos as $c) {
            $t = trim((string) $c);
            if (mb_strlen($t) >= 5) {
                return mb_substr($t, 0, 160);
            }
        }
        foreach ($candidatos as $c) {
            $t = trim((string) $c);
            if ($t !== '') {
                return str_pad($t, 5, '.', STR_PAD_RIGHT);
            }
        }

        return 'Centro';
    }

    /**
     * dgt-xml-generator usa el catálogo actividades-economicas.json con códigos "NNNN.N" (p. ej. 7020.0).
     * Hacienda suele devolver 5 dígitos (70200) o 6 con cero a la izquierda por error (070200); ambos se mapean a 7020.0.
     */
    private function codigoActividadEconomicaParaDgt(string $raw): string
    {
        $raw = trim($raw);
        if (str_contains($raw, '.')) {
            $parts = explode('.', $raw, 2);
            if (
                count($parts) === 2
                && ctype_digit($parts[0])
                && ctype_digit($parts[1])
                && strlen($parts[1]) === 1
            ) {
                return str_pad($parts[0], 4, '0', STR_PAD_LEFT).'.'.$parts[1];
            }
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            throw new InvalidArgumentException(
                'Código de actividad económica inválido. Use el código del catálogo de Hacienda o el formato NNNN.N (ej. 7020.0).'
            );
        }

        if (strlen($digits) > 6) {
            $digits = substr($digits, 0, 6);
        }

        if (strlen($digits) === 6 && str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
            if ($digits === '') {
                $digits = '0';
            }
        }

        if (strlen($digits) <= 5) {
            $digits = str_pad($digits, 5, '0', STR_PAD_LEFT);

            return substr($digits, 0, 4).'.'.substr($digits, 4, 1);
        }

        return substr($digits, 0, 4).'.'.substr($digits, 5, 1);
    }

    private function emisor(Empresa $empresa): array
    {
        $actividadCampo = trim((string) ($empresa->cod_actividad_economica ?? ''));
        if ($actividadCampo === '') {
            throw new InvalidArgumentException(
                'Configure actividad económica del contribuyente (Datos de empresa): cargue actividades desde Hacienda y seleccione la registrada para su NIT.'
            );
        }
        $codAct = $this->codigoActividadEconomicaParaDgt($actividadCampo);

        $nit = $this->soloDigitos((string) ($empresa->nit ?? ''));
        if (strlen($nit) < 9) {
            throw new InvalidArgumentException('El número de identificación del emisor no es válido para Costa Rica (mínimo 9 dígitos, según tipo configurado).');
        }

        $loc = $this->ubicacionEmisor($empresa);

        return [
            'identification_type' => $this->tipoIdentificacionEmisorCr($empresa),
            'identification_number' => $nit,
            'name' => $empresa->nombre ?? 'Emisor',
            'trade_name' => $empresa->nombre ?? null,
            'activity' => $codAct,
            'location' => [
                'province' => $loc['province'],
                'canton' => $loc['canton'],
                'district' => $loc['district'],
                'neighborhood' => $this->textoBarrioUbicacionXml(
                    (string) $empresa->getCustomConfigValue('facturacion_fe', 'emisor_barrio', ''),
                    (string) ($empresa->direccion ?? '')
                ),
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
                'neighborhood' => $this->textoBarrioUbicacionXml(
                    (string) $empresa->getCustomConfigValue('facturacion_fe', 'receptor_barrio', ''),
                    (string) ($cliente->distrito ?? ''),
                    (string) ($cliente->municipio ?? ''),
                    (string) ($cliente->direccion ?? ''),
                    (string) ($empresa->direccion ?? '')
                ),
                'address_details' => $cliente->direccion ?: ($empresa->direccion ?? 'Costa Rica'),
            ],
        ];

        $ractCode = $empresa->getCustomConfigValue('facturacion_fe', 'receptor_actividad_codigo', null);
        if ($ractCode !== null && trim((string) $ractCode) !== '') {
            $receiver['activity'] = $this->codigoActividadEconomicaParaDgt((string) $ractCode);
        }

        if ($cliente->correo) {
            $receiver['email'] = [$cliente->correo];
        }
        if ($cliente->telefono) {
            $receiver['phone'] = $this->telefonoCr($cliente->telefono);
        }

        // dgt-xml-generator siempre lee phone y email en el receptor (sin isset).
        if (! isset($receiver['phone'])) {
            $receiver['phone'] = $this->telefonoCr($empresa->telefono ?? '22222222');
        }
        if (! isset($receiver['email'])) {
            $receiver['email'] = array_filter([$empresa->correo ?? null]);
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
                'neighborhood' => $this->textoBarrioUbicacionXml(
                    (string) $empresa->getCustomConfigValue('facturacion_fe', 'emisor_barrio', ''),
                    (string) ($empresa->direccion ?? '')
                ),
                'address_details' => $empresa->direccion ?? 'Costa Rica',
            ],
            'phone' => $this->telefonoCr($empresa->telefono ?? '22222222'),
            'email' => array_filter([$empresa->correo ?? null]),
        ];
    }

    /**
     * Consumidor final en tiquete electrónico (04): tipo 05 + número placeholder (nota 4 v4.4).
     * No usar 06 aquí: Hacienda rechaza -409 «Tipo de Identificación del Receptor no permitido para este documento».
     */
    private function receptorGenericoTiquete(Empresa $empresa, string $nombre = 'Cliente general'): array
    {
        $base = $this->receptorGenerico($empresa, $nombre);

        return array_replace($base, [
            'identification_type' => '05',
            'identification_number' => '00000000000000000000',
        ]);
    }

    private function cabysPorDefecto(Empresa $empresa): string
    {
        $raw = $empresa->getCustomConfigValue('facturacion_fe', 'cabys_default', null);
        $digits = preg_replace('/\D/', '', (string) $raw);

        return strlen($digits) === 13 ? $digits : '';
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

    /**
     * @see \App\Models\MH\MHFactura::detalles() En El Salvador, si la venta lleva IVA, al generar el DTE se incorpora
     *      el impuesto (p. ej. precio * 1.13) porque en BD los montos suelen venir sin reflejar el total con impuesto
     *      en el mismo sentido. Aquí: si la venta tiene IVA y el detalle tiene `total` = `sub_total` pero con IVA en
     *      columna `iva`, el monto de línea para FE debe ser subtotal + IVA (coherente con resumen y pagos).
     */
    private function linea(Detalle $detalle, Empresa $empresa, Venta $venta): array
    {
        $producto = $detalle->producto;
        $cabys = $this->resolverCabysLinea($producto, $empresa);
        if (strlen($cabys) !== 13) {
            throw new InvalidArgumentException(
                'Código CABYS inválido o faltante. Asigne CABYS en el producto o servicio, producto.codigo con 13 dígitos, o configure facturacion_fe.cabys_default (13 dígitos) si aplica.'
            );
        }

        $cantidad = (float) $detalle->cantidad;
        if ($cantidad <= 0) {
            $cantidad = 1.0;
        }

        $subTotal = round((float) $detalle->sub_total, 5);
        $ivaMonto = round((float) ($detalle->iva ?? 0), 5);
        $totalLinea = round((float) $detalle->total, 5);
        $ventaLlevaIva = (float) ($venta->iva ?? 0) > 0.00001;
        if (
            $ventaLlevaIva
            && $ivaMonto > 0.00001
            && abs($totalLinea - $subTotal) < 0.0001
        ) {
            $totalLinea = round($subTotal + $ivaMonto, 5);
        }

        $pct = (float) ($detalle->porcentaje_impuesto ?? 0);
        [$ivaTarifaCode, , $rate] = $this->tarifaIva($pct, $ivaMonto > 0);

        $esServicio = $this->esLineaServicioCr($producto);

        // transaction_type 01 = catálogo DGT «Venta normal de bienes y servicios»; no usar 02 pensando que es «servicio».
        $line = [
            'cabys_code' => $cabys,
            'description' => mb_substr(strip_tags((string) $detalle->descripcion), 0, 200),
            'quantity' => $cantidad,
            'unit_measure' => $esServicio ? 'Sp' : 'Unid',
            'unit_price' => round($subTotal / $cantidad, 5),
            'sub_total' => $subTotal,
            'total_amount' => $subTotal,
            'taxable_base' => $subTotal,
            'transaction_type' => '01',
            'total_tax' => $ivaMonto,
            'total' => $totalLinea,
        ];

        // XSD v4.4: antes de <ImpuestoNeto> debe existir al menos un <Impuesto> o <ImpuestoAsumidoEmisorFabrica>.
        $gravado = $ivaMonto > 0 && $rate > 0;
        $line['taxes'] = [[
            'tax_type' => '01',
            'iva_type' => $this->codigoTarifaIvaDosDigitos($ivaTarifaCode),
            'rate' => $gravado ? $rate : 0.0,
            'amount' => $gravado ? $ivaMonto : 0.0,
        ]];

        return $line;
    }

    /**
     * Metadatos de exoneración guardados en el JSON del comprobante (trazabilidad).
     * El generador XML actual (dgt-xml-generator) no incluye aún el nodo de exoneración v4.4 en línea.
     *
     * @return array<string, mixed>|null
     */
    private function metadataExoneracionCr(Venta $venta): ?array
    {
        if (! $this->ventaDeclaraExoneracionCr($venta)) {
            return null;
        }

        $ex = $this->exoneracionCrArray($venta);

        return [
            'aplica' => true,
            'tipo_documento_ex' => (string) ($ex['tipo_documento_ex'] ?? ''),
            'numero_documento' => (string) ($ex['numero_documento'] ?? ''),
            'nombre_institucion' => (string) ($ex['nombre_institucion'] ?? ''),
            'tarifa_exonerada' => (float) ($ex['tarifa_exonerada'] ?? 13),
            'numero_articulo' => (string) ($ex['numero_articulo'] ?? ''),
            'numero_inciso' => (string) ($ex['numero_inciso'] ?? ''),
            'documento_otro' => (string) ($ex['documento_otro'] ?? ''),
        ];
    }

    private function ventaDeclaraExoneracionCr(Venta $venta): bool
    {
        $ex = $venta->fe_cr_exoneracion;

        return is_array($ex) && ! empty($ex['aplica']);
    }

    /**
     * @return array<string, mixed>
     */
    private function exoneracionCrArray(Venta $venta): array
    {
        $ex = $venta->fe_cr_exoneracion;

        return is_array($ex) ? $ex : [];
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

    /**
     * Código de tarifa IVA según catálogo DGT (dos dígitos, p. ej. "08"). Evita pérdida del cero inicial si el valor circula como entero.
     */
    private function codigoTarifaIvaDosDigitos(string $code): string
    {
        $d = preg_replace('/\D/', '', $code);

        return $d !== '' ? str_pad($d, 2, '0', STR_PAD_LEFT) : '08';
    }

    private function pagos(Venta $venta): array
    {
        return [[
            'payment_method' => '01',
            'amount' => round((float) $venta->total, 2),
        ]];
    }

    /**
     * Totales de resumen = misma regla que cada línea del XML (Unid vs Sp y base gravada = taxable_base de la línea).
     * Evita -111 cuando columna gravada del detalle ≠ sub_total usado en línea, o cuando servicio/mercancía no coincidía con el detalle.
     *
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    private function resumenAlineadoALineas(Venta $venta, array $lineItems): array
    {
        $venta->loadMissing(['detalles.producto']);

        $taxedGoods = 0.0;
        $taxedServices = 0.0;
        $exemptGoods = 0.0;
        $exemptServices = 0.0;
        $nsGoods = 0.0;
        $nsServices = 0.0;

        $lineItems = array_values($lineItems);
        $detalles = $venta->detalles->values();
        foreach ($detalles as $idx => $detalle) {
            $line = $lineItems[$idx] ?? null;
            if (! is_array($line)) {
                continue;
            }

            $esServicio = $this->lineaUsaUnidadServicioCr($line);
            $clas = $this->clasificarDetalleVentaCr($detalle);

            if ($clas === 'gravada') {
                $monto = round((float) ($line['taxable_base'] ?? $line['sub_total'] ?? 0), 2);
            } else {
                $monto = $this->montoDetallePorClasificacionCr($detalle, $clas);
            }

            if ($monto <= 0.00001) {
                continue;
            }

            if ($clas === 'gravada') {
                if ($esServicio) {
                    $taxedServices += $monto;
                } else {
                    $taxedGoods += $monto;
                }
            } elseif ($clas === 'exenta') {
                if ($esServicio) {
                    $exemptServices += $monto;
                } else {
                    $exemptGoods += $monto;
                }
            } else {
                if ($esServicio) {
                    $nsServices += $monto;
                } else {
                    $nsGoods += $monto;
                }
            }
        }

        $taxedGoods = round($taxedGoods, 2);
        $taxedServices = round($taxedServices, 2);
        $exemptGoods = round($exemptGoods, 2);
        $exemptServices = round($exemptServices, 2);
        $nsGoods = round($nsGoods, 2);
        $nsServices = round($nsServices, 2);

        $iva = round((float) ($venta->iva ?? 0), 2);
        $desc = round((float) ($venta->descuento ?? 0), 2);
        $sub = round((float) ($venta->sub_total ?? 0), 2);
        $total = round((float) ($venta->total ?? 0), 2);

        $totalTaxed = round($taxedGoods + $taxedServices, 2);
        $totalExempt = round($exemptGoods + $exemptServices, 2);
        $totalNs = round($nsGoods + $nsServices, 2);

        $summary = [
            'total_taxed_goods' => $taxedGoods,
            'total_exempt_goods' => $exemptGoods,
            'total_non_taxable_goods' => $nsGoods,
            'total_taxed_services' => $taxedServices,
            'total_exempt_services' => $exemptServices,
            'total_non_taxable_services' => $nsServices,
            'total_taxed' => $totalTaxed,
            'total_exempt' => $totalExempt,
            'total_non_taxable' => $totalNs,
            'total_sale' => $sub + $desc,
            'total_discounts' => $desc,
            'total_net_sale' => $sub,
            'total_tax' => $iva,
            'total' => $total,
        ];

        if ($iva > 0) {
            $summary['taxes'] = [[
                'tax_type' => '01',
                'iva_type' => '08',
                'rate' => 13.0,
                'amount' => $iva,
            ]];
        }

        return $summary;
    }

    /**
     * Coherente con {@see linea()}: UnidadMedida Sp = servicio gravado en resumen; otro código = mercancía.
     *
     * @param  array<string, mixed>  $line
     */
    private function lineaUsaUnidadServicioCr(array $line): bool
    {
        $um = $line['unit_measure'] ?? null;
        if (is_array($um)) {
            return ($um['code'] ?? '') === 'Sp';
        }

        return $um === 'Sp';
    }

    /**
     * Servicio vs bien para Unid vs Sp y totales del resumen: debe alinearse con cómo Hacienda agrupa la línea (evita -111).
     * El catálogo DGT de tipo de transacción (01, 02…) no es «01=mercancía / 02=servicio»; el 01 es venta general.
     * Por defecto se trata como mercancía (Unid) salvo tipo/medida claramente de servicio — no usar palabras sueltas en
     * descripcion_cabys (muchas descripciones oficiales incluyen «servicio» y generaban Sp erróneo en productos físicos).
     */
    /**
     * Línea de FEC compra (08): sin producto asociado suele tratarse como servicio/gasto; evita Unid por defecto y -111 con Hacienda.
     */
    private function esLineaServicioCompraFec(?Producto $producto): bool
    {
        if ($producto === null) {
            return true;
        }

        return $this->esLineaServicioCr($producto);
    }

    private function esLineaServicioCr(?Producto $producto): bool
    {
        if ($producto === null) {
            return false;
        }

        $tipo = trim((string) ($producto->tipo ?? ''));
        if ($tipo !== '') {
            if (
                strcasecmp($tipo, 'Mercancía') === 0
                || strcasecmp($tipo, 'Mercancia') === 0
                || strcasecmp($tipo, 'Producto') === 0
                || strcasecmp($tipo, 'Materia Prima') === 0
                || strcasecmp($tipo, 'Insumo') === 0
            ) {
                return false;
            }
        }

        if (strcasecmp($tipo, 'Servicio') === 0) {
            return true;
        }

        $tipoStr = strtolower($tipo);
        if ($tipoStr !== '' && str_contains($tipoStr, 'servic')) {
            return true;
        }

        $medida = strtoupper(trim((string) ($producto->medida ?? '')));
        if (in_array($medida, ['SP', 'S/P', 'HORA', 'H', 'SERVICIO'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @return 'gravada'|'exenta'|'no_sujeta'
     */
    private function clasificarDetalleVentaCr(Detalle $detalle): string
    {
        $t = strtolower(trim((string) ($detalle->tipo_gravado ?? '')));
        if (in_array($t, ['gravada', 'exenta', 'no_sujeta'], true)) {
            return $t;
        }
        if ((float) ($detalle->iva ?? 0) > 0.00001) {
            return 'gravada';
        }
        if ((float) ($detalle->exenta ?? 0) > 0.00001) {
            return 'exenta';
        }
        if ((float) ($detalle->no_sujeta ?? 0) > 0.00001) {
            return 'no_sujeta';
        }

        return 'gravada';
    }

    private function montoDetallePorClasificacionCr(Detalle $detalle, string $clasificacion): float
    {
        return match ($clasificacion) {
            'gravada' => $this->montoGravadoLineaResumenCr($detalle),
            'exenta' => round(max((float) ($detalle->exenta ?? 0), 0), 2) > 0.00001
                ? round((float) $detalle->exenta, 2)
                : round((float) $detalle->sub_total, 2),
            'no_sujeta' => round(max((float) ($detalle->no_sujeta ?? 0), 0), 2) > 0.00001
                ? round((float) $detalle->no_sujeta, 2)
                : round((float) $detalle->sub_total, 2),
            default => round((float) $detalle->sub_total, 2),
        };
    }

    private function montoGravadoLineaResumenCr(Detalle $detalle): float
    {
        $grav = round((float) ($detalle->gravada ?? 0), 2);
        if ($grav > 0.00001) {
            return $grav;
        }
        if ((float) ($detalle->iva ?? 0) > 0.00001) {
            return round((float) $detalle->sub_total, 2);
        }

        return 0.0;
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

    /**
     * Factura electrónica de compras (08): emisor = empresa compradora, receptor = proveedor vendedor.
     *
     * @return array<string, mixed>
     */
    public function buildFacturaElectronicaCompraDesdeCompra(Compra $compra, Empresa $empresa, int $secuencial): array
    {
        $compra->loadMissing(['detalles.producto', 'proveedor', 'sucursal']);
        if ($compra->detalles->isEmpty()) {
            throw new InvalidArgumentException('La compra no tiene líneas de detalle para FEC.');
        }
        $proveedor = $compra->proveedor;
        if (! $proveedor instanceof Proveedor) {
            throw new InvalidArgumentException('Indique un proveedor para emitir la factura electrónica de compra.');
        }

        $fecha = Carbon::now('America/Costa_Rica');
        $dateIso = $fecha->format('Y-m-d\TH:i:sP');
        [$est, $ter] = $this->establecimientoYTerminalCr($empresa, $compra->sucursal);
        $seq = str_pad((string) $secuencial, 10, '0', STR_PAD_LEFT);
        $moneda = strtoupper((string) ($empresa->moneda ?? 'CRC')) === 'USD' ? 'USD' : 'CRC';
        $tipoCambio = $moneda === 'USD' ? $this->tipoCambio->crcPorUsdVenta($empresa) : 1.0;

        // Índices 0..n-1: el resumen empareja por índice con detalles; sin values() el map puede conservar keys del modelo y desalinear servicios vs mercancías (-111 Hacienda).
        $lineItems = array_values($compra->detalles->map(fn (DetalleCompra $d) => $this->lineaCompra($d, $empresa, $compra))->all());

        return [
            'date' => $dateIso,
            'establishment' => $est,
            'emission_point' => $ter,
            'sequential' => $seq,
            'security_key' => $this->claveSeguridad8(),
            'situation' => 1,
            'sale_condition' => $this->condicionCompraOGasto($compra->condicion ?? 'contado'),
            'currency' => [
                'currency_code' => $moneda,
                'exchange_rate' => round($tipoCambio, 5),
            ],
            'issuer' => $this->emisor($empresa),
            'receiver' => $this->receptorProveedor($proveedor, $empresa),
            'line_items' => $lineItems,
            'payments' => $this->pagosDesdeMonto((float) $compra->total),
            'summary' => $this->resumenCompraAlineadoLineas($compra, $lineItems),
            'referenced_documents' => $this->referencedDocumentsFacturaElectronicaCompra(
                $dateIso,
                $compra->fecha ?? null,
                $compra->referencia ?? null,
                $compra->numero_control ?? null,
                (int) $compra->id
            ),
        ];
    }

    /**
     * FEC desde egreso/gasto con líneas de detalle (mismo comprobante 08).
     *
     * @return array<string, mixed>
     */
    public function buildFacturaElectronicaCompraDesdeGasto(Gasto $gasto, Empresa $empresa, int $secuencial): array
    {
        $gasto->loadMissing(['detalles', 'proveedor', 'sucursal']);
        if ($gasto->detalles->isEmpty()) {
            throw new InvalidArgumentException('El gasto no tiene líneas de detalle para FEC.');
        }
        $proveedor = $gasto->proveedor;
        if (! $proveedor instanceof Proveedor) {
            throw new InvalidArgumentException('Indique un proveedor para emitir la factura electrónica de compra.');
        }

        $fecha = Carbon::now('America/Costa_Rica');
        $dateIso = $fecha->format('Y-m-d\TH:i:sP');
        [$est, $ter] = $this->establecimientoYTerminalCr($empresa, $gasto->sucursal);
        $seq = str_pad((string) $secuencial, 10, '0', STR_PAD_LEFT);
        $moneda = strtoupper((string) ($empresa->moneda ?? 'CRC')) === 'USD' ? 'USD' : 'CRC';
        $tipoCambio = $moneda === 'USD' ? $this->tipoCambio->crcPorUsdVenta($empresa) : 1.0;

        $lineItems = array_values($gasto->detalles->map(fn (DetalleEgreso $d) => $this->lineaGastoFec($d, $empresa, $gasto))->all());

        return [
            'date' => $dateIso,
            'establishment' => $est,
            'emission_point' => $ter,
            'sequential' => $seq,
            'security_key' => $this->claveSeguridad8(),
            'situation' => 1,
            'sale_condition' => $this->condicionCompraOGasto($gasto->condicion ?? 'contado'),
            'currency' => [
                'currency_code' => $moneda,
                'exchange_rate' => round($tipoCambio, 5),
            ],
            'issuer' => $this->emisor($empresa),
            'receiver' => $this->receptorProveedor($proveedor, $empresa),
            'line_items' => $lineItems,
            'payments' => $this->pagosDesdeMonto((float) $gasto->total),
            'summary' => $this->resumenGastoFecAlineadoLineas($gasto, $lineItems),
            'referenced_documents' => $this->referencedDocumentsFacturaElectronicaCompra(
                $dateIso,
                $gasto->fecha ?? null,
                $gasto->referencia ?? null,
                $gasto->numero_control ?? null,
                (int) $gasto->id
            ),
        ];
    }

    /**
     * XSD v4.4 (facturaElectronicaCompra): el nodo InformacionReferencia tiene minOccurs 1; sin él, la firma viola la secuencia (cvc-complex-type.2.4.a).
     *
     * @return array<int, array<string, mixed>>
     */
    private function referencedDocumentsFacturaElectronicaCompra(
        string $dateIsoEmisionFec,
        mixed $fechaRegistroCompra,
        mixed $referencia,
        mixed $numeroControl,
        int $idRegistro
    ): array {
        $fechaRef = $dateIsoEmisionFec;
        if ($fechaRegistroCompra !== null && trim((string) $fechaRegistroCompra) !== '') {
            try {
                $fechaRef = Carbon::parse($fechaRegistroCompra)->timezone('America/Costa_Rica')->format('Y-m-d\TH:i:sP');
            } catch (\Throwable) {
                // conservar fecha de emisión del FEC
            }
        }

        $docRef = trim((string) $referencia);
        if ($docRef === '') {
            $docRef = trim((string) $numeroControl);
        }
        if ($docRef !== '') {
            // Tipo 01: Hacienda valida que Numero sea clave de comprobante electrónico (50 dígitos); otras referencias → 99.
            if ($this->esClaveComprobanteElectronicoCr($docRef)) {
                $clave50 = preg_replace('/\D/', '', $docRef);

                return [[
                    'document_type' => '01',
                    'document_number' => $clave50,
                    'emission_date' => $fechaRef,
                    'referenced_code' => '04',
                    'reason' => mb_substr('Referencia documento proveedor; registro #'.$idRegistro, 0, 180),
                ]];
            }

            $otro = mb_substr('Ref. proveedor: '.$docRef, 0, 100);
            if (mb_strlen(trim($otro)) < 5) {
                $otro = 'Ref. proveedor documento';
            }

            return [[
                'document_type' => '99',
                'other_document_type' => $otro,
                'document_number' => (string) $idRegistro,
                'emission_date' => $fechaRef,
                'referenced_code' => '04',
                'reason' => mb_substr('Documento no electrónico o sin clave DGT; registro #'.$idRegistro, 0, 180),
            ]];
        }

        return [[
            'document_type' => '99',
            'other_document_type' => 'Registro de compra sin documento de referencia del proveedor',
            'document_number' => (string) $idRegistro,
            'emission_date' => $dateIsoEmisionFec,
            'referenced_code' => '04',
            'reason' => mb_substr('Sin número de documento del proveedor; registro #'.$idRegistro, 0, 180),
        ]];
    }

    /**
     * Clave numérica de comprobante electrónico CR: 50 dígitos (Hacienda rechaza -29 si no coincide).
     */
    private function esClaveComprobanteElectronicoCr(string $s): bool
    {
        $d = preg_replace('/\D/', '', $s);

        return strlen($d) === 50;
    }

    private function condicionCompraOGasto(string $condicion): string
    {
        $c = strtolower(trim($condicion));

        return str_contains($c, 'cred') ? '02' : '01';
    }

    /**
     * @return array<string, mixed>
     */
    private function receptorProveedor(Proveedor $proveedor, Empresa $empresaCompradora): array
    {
        $nit = $this->soloDigitos((string) ($proveedor->nit ?? ''));
        $dui = $this->soloDigitos((string) ($proveedor->dui ?? ''));
        if (strlen($nit) >= 9) {
            $tipo = '02';
            $num = $nit;
            $nombre = (string) ($proveedor->nombre_empresa ?: trim(($proveedor->nombre ?? '').' '.($proveedor->apellido ?? '')));
        } elseif (strlen($dui) >= 9) {
            $tipo = '01';
            $num = substr(str_pad($dui, 9, '0', STR_PAD_LEFT), 0, 9);
            $nombre = trim(($proveedor->nombre ?? '').' '.($proveedor->apellido ?? ''));
        } else {
            $tipo = '06';
            $num = '00000000000000';
            $nombre = $proveedor->tipo === 'Empresa'
                ? (string) ($proveedor->nombre_empresa ?? 'Proveedor')
                : trim(($proveedor->nombre ?? '').' '.($proveedor->apellido ?? ''));
        }

        $loc = $this->ubicacionProveedorOEmpresa($proveedor, $empresaCompradora);

        $receiver = [
            'identification_type' => $tipo,
            'identification_number' => $num,
            'name' => $nombre !== '' ? mb_substr($nombre, 0, 100) : 'Proveedor',
            'location' => $loc,
        ];

        // FEC (08): Hacienda (XSD) exige CodigoActividadReceptor antes de NumeroConsecutivo con código CIIU válido; no admite omitir el nodo ni vacío.
        $codReceptorAct = null;
        $ractCfg = $empresaCompradora->getCustomConfigValue('facturacion_fe', 'receptor_actividad_codigo', null);
        if ($ractCfg !== null && trim((string) $ractCfg) !== '') {
            $codReceptorAct = trim((string) $ractCfg);
        } elseif (! empty($proveedor->cod_giro)) {
            $codReceptorAct = trim((string) $proveedor->cod_giro);
        }
        if ($codReceptorAct === null || $codReceptorAct === '') {
            throw new InvalidArgumentException(
                'La factura electrónica de compra (FEC) requiere el código de actividad económica (CIIU) del proveedor. Edite el proveedor en Compras → Proveedores y seleccione el giro/actividad del catálogo; o defina en la empresa un código de actividad del receptor para FE (configuración personalizada de facturación electrónica).'
            );
        }
        $receiver['activity'] = $this->codigoActividadEconomicaParaDgt($codReceptorAct);

        if ($proveedor->correo) {
            $receiver['email'] = [$proveedor->correo];
        }
        if ($proveedor->telefono) {
            $receiver['phone'] = $this->telefonoCr($proveedor->telefono);
        }
        if (! isset($receiver['phone'])) {
            $receiver['phone'] = $this->telefonoCr($empresaCompradora->telefono ?? '22222222');
        }
        if (! isset($receiver['email'])) {
            $receiver['email'] = array_filter([$empresaCompradora->correo ?? null]);
        }

        return $receiver;
    }

    /**
     * @return array<string, mixed>
     */
    private function ubicacionProveedorOEmpresa(Proveedor $proveedor, Empresa $empresa): array
    {
        $rawDist = $proveedor->cod_distrito ?? null;
        $d = preg_replace('/\D/', '', (string) $rawDist);
        if (strlen($d) === 5 && preg_match('/^[1-7]\d{4}$/', $d) === 1) {
            return [
                'province' => (int) $d[0],
                'canton' => substr($d, 0, 3),
                'district' => $d,
                'neighborhood' => $this->textoBarrioUbicacionXml(
                    (string) ($proveedor->distrito ?? ''),
                    (string) ($proveedor->direccion ?? '')
                ),
                'address_details' => $proveedor->direccion ?: ($empresa->direccion ?? 'Costa Rica'),
            ];
        }

        $loc = $this->ubicacionEmisor($empresa);

        return [
            'province' => $loc['province'],
            'canton' => $loc['canton'],
            'district' => $loc['district'],
            'neighborhood' => $this->textoBarrioUbicacionXml(
                (string) $empresa->getCustomConfigValue('facturacion_fe', 'emisor_barrio', ''),
                (string) ($proveedor->direccion ?? ''),
                (string) ($empresa->direccion ?? '')
            ),
            'address_details' => $proveedor->direccion ?: ($empresa->direccion ?? 'Costa Rica'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineaCompra(DetalleCompra $detalle, Empresa $empresa, Compra $compra): array
    {
        $detalle->loadMissing('producto');
        $producto = $detalle->producto;
        $cabys = $this->resolverCabysLinea($producto, $empresa);
        if (strlen($cabys) !== 13) {
            throw new InvalidArgumentException(
                'Código CABYS inválido o faltante en línea de compra. Asigne CABYS al producto o facturacion_fe.cabys_default.'
            );
        }

        $cantidad = (float) $detalle->cantidad;
        if ($cantidad <= 0) {
            $cantidad = 1.0;
        }

        $subTotal = $this->subtotalLineaDetalleCompra($detalle, $cantidad);
        if ($subTotal <= 0.00001) {
            throw new InvalidArgumentException(
                'FEC: el subtotal de cada línea de compra debe ser mayor a cero. En el detalle indique subtotal, o costo × cantidad, o total con IVA.'
            );
        }

        $ivaMonto = round((float) ($detalle->iva ?? 0), 5);
        $totalLinea = round((float) ($detalle->total ?? 0), 5);
        $compraLlevaIva = (float) ($compra->iva ?? 0) > 0.00001;
        $clasLinea = $this->clasificarDetalleCompraCr($detalle);

        // IVA solo en encabezado de compra pero líneas sin iva: repartir o 13 % sobre base gravada (-45/-46/-51).
        if ($clasLinea === 'gravada' && $compraLlevaIva && $ivaMonto < 0.00001) {
            $sumGrav = $this->sumSubtotalGravadaCompra($compra);
            $ivaCompra = round((float) ($compra->iva ?? 0), 5);
            if ($sumGrav > 0.00001) {
                $ivaMonto = round($ivaCompra * ($subTotal / $sumGrav), 5);
            } else {
                $ivaMonto = round($subTotal * 0.13, 5);
            }
        }

        if (
            $compraLlevaIva
            && $ivaMonto > 0.00001
            && abs($totalLinea - $subTotal) < 0.0001
        ) {
            $totalLinea = round($subTotal + $ivaMonto, 5);
        }

        if ($totalLinea <= 0.00001) {
            $totalLinea = round($subTotal + $ivaMonto, 5);
        }

        $pct = (float) ($detalle->porcentaje_impuesto ?? 0);
        if ($ivaMonto > 0.00001 && $pct < 0.01) {
            $pct = 13.0;
        }
        [$ivaTarifaCode, , $rate] = $this->tarifaIva($pct, $ivaMonto > 0.00001);

        $esServicio = $this->esLineaServicioCompraFec($producto);

        $desc = $detalle->nombre_producto ?? ($producto->nombre ?? 'Ítem');

        $line = [
            'cabys_code' => $cabys,
            'description' => mb_substr(strip_tags((string) $desc), 0, 200),
            'quantity' => $cantidad,
            'unit_measure' => $esServicio ? 'Sp' : 'Unid',
            'unit_price' => round($subTotal / $cantidad, 5),
            'sub_total' => $subTotal,
            'total_amount' => $subTotal,
            'taxable_base' => $subTotal,
            'transaction_type' => '01',
            'total_tax' => $ivaMonto,
            'total' => $totalLinea,
        ];

        $gravado = $ivaMonto > 0.00001 && $rate > 0;
        $line['taxes'] = [[
            'tax_type' => '01',
            'iva_type' => $this->codigoTarifaIvaDosDigitos($ivaTarifaCode),
            'rate' => $gravado ? $rate : 0.0,
            'amount' => $gravado ? $ivaMonto : 0.0,
        ]];

        return $line;
    }

    private function sumSubtotalGravadaCompra(Compra $compra): float
    {
        $s = 0.0;
        foreach ($compra->detalles as $d) {
            if ($this->clasificarDetalleCompraCr($d) !== 'gravada') {
                continue;
            }
            $q = (float) ($d->cantidad ?? 0);
            if ($q <= 0) {
                $q = 1.0;
            }
            $s += $this->subtotalLineaDetalleCompra($d, $q);
        }

        return round($s, 5);
    }

    /**
     * En compras a veces solo vienen costo/cantidad y total; subtotal puede ir en 0.
     */
    private function subtotalLineaDetalleCompra(DetalleCompra $detalle, float $cantidadEfectiva): float
    {
        $s = round((float) ($detalle->subtotal ?? $detalle->sub_total ?? 0), 5);
        if ($s > 0.00001) {
            return $s;
        }

        $costo = (float) ($detalle->costo ?? 0);
        $desc = round((float) ($detalle->descuento ?? 0), 5);
        $fromCost = round(max(0.0, $costo * $cantidadEfectiva - $desc), 5);
        if ($fromCost > 0.00001) {
            return $fromCost;
        }

        $total = round((float) ($detalle->total ?? 0), 5);
        $iva = round((float) ($detalle->iva ?? 0), 5);
        if ($total > 0.00001) {
            if ($iva > 0.00001 && $total + 0.00001 >= $iva) {
                return round($total - $iva, 5);
            }

            return $total;
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function lineaGastoFec(DetalleEgreso $detalle, Empresa $empresa, Gasto $gasto): array
    {
        $cabys = $this->cabysPorDefecto($empresa);
        if (strlen($cabys) !== 13) {
            throw new InvalidArgumentException(
                'Para FEC de gasto configure facturacion_fe.cabys_default (13 dígitos) o use compras con productos CABYS.'
            );
        }

        $cantidad = (float) $detalle->cantidad;
        if ($cantidad <= 0) {
            $cantidad = 1.0;
        }

        $subTotal = $this->subtotalLineaDetalleEgreso($detalle, $cantidad);
        if ($subTotal <= 0.00001) {
            throw new InvalidArgumentException(
                'FEC: el subtotal de cada línea de gasto debe ser mayor a cero. Indique sub_total, precio unitario × cantidad o total con IVA.'
            );
        }

        $ivaMonto = round((float) ($detalle->iva ?? 0), 5);
        $totalLinea = round((float) ($detalle->total ?? 0), 5);
        $gastoLlevaIva = (float) ($gasto->iva ?? 0) > 0.00001;
        if ($gastoLlevaIva && $ivaMonto < 0.00001) {
            $sumGrav = $this->sumSubtotalGravadaGasto($gasto);
            $ivaGasto = round((float) ($gasto->iva ?? 0), 5);
            if ($sumGrav > 0.00001) {
                $ivaMonto = round($ivaGasto * ($subTotal / $sumGrav), 5);
            } else {
                $ivaMonto = round($subTotal * 0.13, 5);
            }
        }

        if (
            $gastoLlevaIva
            && $ivaMonto > 0.00001
            && abs($totalLinea - $subTotal) < 0.0001
        ) {
            $totalLinea = round($subTotal + $ivaMonto, 5);
        }

        if ($totalLinea <= 0.00001) {
            $totalLinea = round($subTotal + $ivaMonto, 5);
        }

        $pct = 13.0;
        if ($ivaMonto < 0.00001) {
            $pct = 0.0;
        }
        [$ivaTarifaCode, , $rate] = $this->tarifaIva($pct, $ivaMonto > 0.00001);

        $desc = (string) ($detalle->concepto ?? 'Gasto');

        $line = [
            'cabys_code' => $cabys,
            'description' => mb_substr(strip_tags($desc), 0, 200),
            'quantity' => $cantidad,
            'unit_measure' => 'Sp',
            'unit_price' => round($subTotal / $cantidad, 5),
            'sub_total' => $subTotal,
            'total_amount' => $subTotal,
            'taxable_base' => $subTotal,
            'transaction_type' => '01',
            'total_tax' => $ivaMonto,
            'total' => $totalLinea,
        ];

        $gravado = $ivaMonto > 0.00001 && $rate > 0;
        $line['taxes'] = [[
            'tax_type' => '01',
            'iva_type' => $this->codigoTarifaIvaDosDigitos($ivaTarifaCode),
            'rate' => $gravado ? $rate : 0.0,
            'amount' => $gravado ? $ivaMonto : 0.0,
        ]];

        return $line;
    }

    private function sumSubtotalGravadaGasto(Gasto $gasto): float
    {
        $s = 0.0;
        foreach ($gasto->detalles as $d) {
            $q = (float) ($d->cantidad ?? 0);
            if ($q <= 0) {
                $q = 1.0;
            }
            $s += $this->subtotalLineaDetalleEgreso($d, $q);
        }

        return round($s, 5);
    }

    private function subtotalLineaDetalleEgreso(DetalleEgreso $detalle, float $cantidadEfectiva): float
    {
        $s = round((float) ($detalle->sub_total ?? 0), 5);
        if ($s > 0.00001) {
            return $s;
        }

        $pu = (float) ($detalle->precio_unitario ?? 0);
        $fromPu = round($pu * $cantidadEfectiva, 5);
        if ($fromPu > 0.00001) {
            return $fromPu;
        }

        $total = round((float) ($detalle->total ?? 0), 5);
        $iva = round((float) ($detalle->iva ?? 0), 5);
        if ($total > 0.00001) {
            if ($iva > 0.00001 && $total + 0.00001 >= $iva) {
                return round($total - $iva, 5);
            }

            return $total;
        }

        return 0.0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<string, mixed>
     */
    private function resumenCompraAlineadoLineas(Compra $compra, array $lineItems): array
    {
        $compra->loadMissing(['detalles.producto']);
        $lineItems = array_values($lineItems);

        $taxedGoods = 0.0;
        $taxedServices = 0.0;
        $exemptGoods = 0.0;
        $exemptServices = 0.0;
        $nsGoods = 0.0;
        $nsServices = 0.0;

        foreach ($compra->detalles->values() as $idx => $detalle) {
            $line = $lineItems[$idx] ?? null;
            if (! is_array($line)) {
                continue;
            }
            // Misma regla que ventas y que el XML (Sp vs Unid); evita -111 si unit_measure circula como array ['code'=>'Sp'].
            $esServicio = $this->lineaUsaUnidadServicioCr($line);
            $clas = $this->clasificarDetalleCompraCr($detalle);
            if ($clas === 'gravada') {
                $monto = round((float) ($line['taxable_base'] ?? $line['sub_total'] ?? 0), 2);
            } else {
                $monto = $this->montoDetalleCompraPorClasificacion($detalle, $clas);
            }
            if ($monto <= 0.00001) {
                continue;
            }
            if ($clas === 'gravada') {
                if ($esServicio) {
                    $taxedServices += $monto;
                } else {
                    $taxedGoods += $monto;
                }
            } elseif ($clas === 'exenta') {
                if ($esServicio) {
                    $exemptServices += $monto;
                } else {
                    $exemptGoods += $monto;
                }
            } else {
                if ($esServicio) {
                    $nsServices += $monto;
                } else {
                    $nsGoods += $monto;
                }
            }
        }

        $iva = round((float) ($compra->iva ?? 0), 2);
        $desc = round((float) ($compra->descuento ?? 0), 2);
        $total = round((float) ($compra->total ?? 0), 2);

        $totalTaxed = round($taxedGoods + $taxedServices, 2);
        $totalExempt = round($exemptGoods + $exemptServices, 2);
        $totalNs = round($nsGoods + $nsServices, 2);
        $totalExonerado = 0.0;

        // TotalVenta (Hacienda -51) = TotalGravado + TotalExento + TotalExonerado + TotalNoSujeto
        $totalVenta = round($totalTaxed + $totalExempt + $totalNs + $totalExonerado, 2);

        $summary = [
            'total_taxed_goods' => round($taxedGoods, 2),
            'total_exempt_goods' => round($exemptGoods, 2),
            'total_non_taxable_goods' => round($nsGoods, 2),
            'total_taxed_services' => round($taxedServices, 2),
            'total_exempt_services' => round($exemptServices, 2),
            'total_non_taxable_services' => round($nsServices, 2),
            'total_taxed' => $totalTaxed,
            'total_exempt' => $totalExempt,
            'total_non_taxable' => $totalNs,
            'total_exonerated' => $totalExonerado,
            'total_sale' => $totalVenta,
            'total_discounts' => $desc,
            'total_net_sale' => round(max(0, $totalVenta - $desc), 2),
            'total_tax' => $iva,
            'total' => $total,
        ];

        if ($iva > 0) {
            $summary['taxes'] = [[
                'tax_type' => '01',
                'iva_type' => '08',
                'rate' => 13.0,
                'amount' => $iva,
            ]];
        }

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<string, mixed>
     */
    private function resumenGastoFecAlineadoLineas(Gasto $gasto, array $lineItems): array
    {
        $lineItems = array_values($lineItems);
        $taxedServices = 0.0;
        foreach ($lineItems as $line) {
            if (! is_array($line)) {
                continue;
            }
            $taxedServices += round((float) ($line['taxable_base'] ?? $line['sub_total'] ?? 0), 2);
        }
        $iva = round((float) ($gasto->iva ?? 0), 2);
        $total = round((float) ($gasto->total ?? 0), 2);

        $totalTaxed = round($taxedServices, 2);
        $totalVenta = round($totalTaxed + 0.0 + 0.0 + 0.0, 2);

        $summary = [
            'total_taxed_goods' => 0.0,
            'total_exempt_goods' => 0.0,
            'total_non_taxable_goods' => 0.0,
            'total_taxed_services' => $totalTaxed,
            'total_exempt_services' => 0.0,
            'total_non_taxable_services' => 0.0,
            'total_taxed' => $totalTaxed,
            'total_exempt' => 0.0,
            'total_non_taxable' => 0.0,
            'total_exonerated' => 0.0,
            'total_sale' => $totalVenta,
            'total_discounts' => 0.0,
            'total_net_sale' => $totalVenta,
            'total_tax' => $iva,
            'total' => $total,
        ];

        if ($iva > 0) {
            $summary['taxes'] = [[
                'tax_type' => '01',
                'iva_type' => '08',
                'rate' => 13.0,
                'amount' => $iva,
            ]];
        }

        return $summary;
    }

    private function clasificarDetalleCompraCr(DetalleCompra $detalle): string
    {
        if ((float) ($detalle->iva ?? 0) > 0.00001) {
            return 'gravada';
        }
        if ((float) ($detalle->exenta ?? 0) > 0.00001) {
            return 'exenta';
        }
        if ((float) ($detalle->no_sujeta ?? 0) > 0.00001) {
            return 'no_sujeta';
        }

        return 'gravada';
    }

    private function montoDetalleCompraPorClasificacion(DetalleCompra $detalle, string $clasificacion): float
    {
        $q = (float) ($detalle->cantidad ?? 0);
        if ($q <= 0) {
            $q = 1.0;
        }
        $sub = $this->subtotalLineaDetalleCompra($detalle, $q);

        return match ($clasificacion) {
            'gravada' => $sub,
            'exenta' => round((float) ($detalle->exenta ?? 0), 2) > 0.00001
                ? round((float) $detalle->exenta, 2)
                : $sub,
            'no_sujeta' => round((float) ($detalle->no_sujeta ?? 0), 2) > 0.00001
                ? round((float) $detalle->no_sujeta, 2)
                : $sub,
            default => $sub,
        };
    }
}
