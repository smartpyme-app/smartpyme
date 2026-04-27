<?php

namespace App\Exports\Contabilidad\CostaRica;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Columnas alineadas con plantilla Reporte_Detalle_IVA_Compras (sin ExoPorc). */
final class ReporteDetalleIvaComprasExport implements FromCollection, WithHeadings
{
    /** @param  array<int, array<string, mixed>>|Collection<int, array<string, mixed>>  $filas */
    public function __construct(
        private readonly array|Collection $filas
    ) {}

    public function collection(): Collection
    {
        $c = is_array($this->filas) ? collect($this->filas) : $this->filas;

        return $c->map(function (array $r) {
            return [
                $r['nombre_emisor'] ?? '',
                $r['rfc_emisor'] ?? '',
                $r['fecha'] ?? '',
                $r['nombre_receptor'] ?? '',
                $r['rfc_receptor'] ?? '',
                $r['tipo_doc'] ?? '',
                (int) ($r['cant_lineas'] ?? 0),
                $r['exoneracion'] ?? '',
                (float) ($r['retenciones'] ?? 0),
                $r['folio'] ?? '',
                $r['clave'] ?? '',
                $r['medio_pago'] ?? '',
                $r['cod_moneda'] ?? '',
                (float) ($r['tipo_cambio'] ?? 0),
                (float) ($r['subtotal_13'] ?? 0),
                (float) ($r['subtotal_8'] ?? 0),
                (float) ($r['subtotal_4'] ?? 0),
                (float) ($r['subtotal_2'] ?? 0),
                (float) ($r['subtotal_1'] ?? 0),
                (float) ($r['subtotal_exonerado'] ?? 0),
                (float) ($r['subtotal_exento'] ?? 0),
                (float) ($r['subtotal_gravado'] ?? 0),
                (float) ($r['iva_13'] ?? 0),
                (float) ($r['iva_8'] ?? 0),
                (float) ($r['iva_4'] ?? 0),
                (float) ($r['iva_2'] ?? 0),
                (float) ($r['iva_1'] ?? 0),
                (float) ($r['iva_devuelto'] ?? 0),
            ];
        });
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'NombreEmisor',
            'RFC_Emisor',
            'Fecha',
            'NombreReceptor',
            'RFC_Receptor',
            'TipoDoc',
            'CantLineas',
            'Exoneracion',
            'Retenciones',
            'Folio',
            'Clave',
            'MedioPago',
            'CodMoneda',
            'TipoCambio',
            'Subtotal13',
            'Subtotal8',
            'Subtotal4',
            'Subtotal2',
            'Subtotal1',
            'SubtotalExonerado',
            'SubtotalExento',
            'SubtotalGravado',
            'IVA13',
            'IVA8',
            'IVA4',
            'IVA2',
            'IVA1',
            'IVADevuelto',
        ];
    }
}
