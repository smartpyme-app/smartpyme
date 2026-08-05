<?php

namespace App\Support\Honduras;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use Carbon\Carbon;

final class DocumentoImpresionHn
{
    public const VISTA_DEFAULT = 'reportes.facturacion.formatos_pais.default-honduras';

    public const NOMBRES_FISCALES = [
        'Factura con RTN',
        'Factura sin RTN',
        'Ticket',
        'Boleta de compra',
        'Nota de crédito',
        'Nota de débito',
        'Recibo por honorarios profesionales',
        'Guía de remisión',
        'Comprobante de retención',
    ];

    public const NOMBRES_FACTURA = [
        'Factura',
        'Factura con RTN',
        'Factura sin RTN',
    ];

    /** Plantillas de factura propias de empresas hondureñas; el resto usa el default del país. */
    public const VISTAS_FACTURA_EMPRESA = [
        420 => 'reportes.facturacion.formatos_empresas.Factura-Inversiones-Andre',
        614 => 'reportes.facturacion.formatos_empresas.Factura-Accesorios-Honduras',
        700 => 'reportes.facturacion.formatos_empresas.Factura-Lilian-Ohle',
    ];

    /** Los formatos de factura (térmicos o carta) no sirven para notas, guías ni boletas. */
    public static function esFactura(?string $nombre): bool
    {
        return in_array($nombre, self::NOMBRES_FACTURA, true);
    }

    public static function usaTicketAccesorios(Empresa $empresa, ?string $nombreDocumento): bool
    {
        $configuraciones = $empresa->custom_empresa['configuraciones'] ?? [];

        return FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa)
                === FacturacionElectronicaCountryResolver::CODIGO_HONDURAS
            && self::esFactura($nombreDocumento)
            && (
                (int) $empresa->id === 716
                || (bool) ($configuraciones['factura_ticket_accesorios_hn'] ?? false)
            );
    }

    /** Los formatos HN muestran los centavos como dígitos, no en letras. */
    public static function centavos(float|int|string $total): string
    {
        $partes = explode('.', number_format((float) $total, 2, '.', ''));

        return str_pad($partes[1] ?? '00', 2, '0', STR_PAD_LEFT);
    }

    public static function aplica(Empresa $empresa, Documento $documento): bool
    {
        return FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa)
                === FacturacionElectronicaCountryResolver::CODIGO_HONDURAS
            && in_array($documento->nombre, self::NOMBRES_FISCALES, true);
    }

    /** Notas, guías y boletas nunca heredan la plantilla de factura de la empresa. */
    public static function vistaFacturaEmpresa(Empresa $empresa, Documento $documento): ?string
    {
        return self::esFactura($documento->nombre)
            ? (self::VISTAS_FACTURA_EMPRESA[(int) $empresa->id] ?? null)
            : null;
    }

    public static function resolverVista(Empresa $empresa, Documento $documento): ?string
    {
        if (!self::aplica($empresa, $documento)) {
            return null;
        }

        return self::vistaFacturaEmpresa($empresa, $documento) ?? self::VISTA_DEFAULT;
    }

    public static function correlativo(Documento $documento, string|int|null $correlativo): string
    {
        return FormatoCorrelativoHn::format($documento->numero_emision, $correlativo);
    }

    public static function footer(Documento $documento): array
    {
        return [
            'nota' => trim((string) $documento->nota) ?: null,
            'cai' => trim((string) $documento->resolucion) ?: null,
            'rango' => trim((string) $documento->rangos) ?: null,
            'fecha_limite' => $documento->fecha ? Carbon::parse($documento->fecha)->format('d/m/Y') : null,
        ];
    }

    public static function totales(iterable $detalles, float $ivaEmpresa): array
    {
        $totales = [
            'exonerado' => 0.0,
            'exento' => 0.0,
            'gravado_15' => 0.0,
            'gravado_18' => 0.0,
            'isv_15' => 0.0,
            'isv_18' => 0.0,
            'descuento' => 0.0,
        ];

        foreach ($detalles as $detalle) {
            $tipo = (string) ($detalle->tipo_gravado ?? 'gravada');
            $tasa = self::numero($detalle->porcentaje_impuesto ?? null) ?? $ivaEmpresa;
            $base = self::primerMonto($detalle->gravada ?? null, $detalle->sub_total ?? null, $detalle->total ?? null);
            $impuesto = (float) self::numero($detalle->iva ?? null);
            $totales['descuento'] += (float) self::numero($detalle->descuento ?? null);

            if ($tipo === 'exonerada' || $tipo === 'no_sujeta') {
                $totales['exonerado'] += $tipo === 'no_sujeta'
                    ? self::primerMonto($detalle->no_sujeta ?? null, $base)
                    : $base;
            } elseif ($tipo === 'exenta' || abs($tasa) < 0.01) {
                $totales['exento'] += self::primerMonto($detalle->exenta ?? null, $base);
            } elseif (abs($tasa - 18) < 0.01) {
                $totales['gravado_18'] += $base;
                $totales['isv_18'] += $impuesto;
            } else {
                $totales['gravado_15'] += $base;
                $totales['isv_15'] += $impuesto;
            }
        }

        return $totales;
    }

    /** Vacíos y textos no numéricos no son un cero fiscal: devuelven null para que decida el llamador. */
    private static function numero(mixed $valor): ?float
    {
        if ($valor === null || (is_string($valor) && !is_numeric(trim($valor)))) {
            return null;
        }

        return (float) $valor;
    }

    /** Primer monto distinto de cero: los detalles guardan 0 en la columna que no aplica. */
    private static function primerMonto(mixed ...$valores): float
    {
        foreach ($valores as $valor) {
            $monto = self::numero($valor) ?? 0.0;
            if ($monto !== 0.0) {
                return $monto;
            }
        }

        return 0.0;
    }
}
