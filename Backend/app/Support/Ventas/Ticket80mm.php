<?php

namespace App\Support\Ventas;

use App\Models\Admin\Documento;
use App\Models\Admin\Empresa;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use App\Support\Admin\DocumentosDefaultPorPais;
use App\Support\Honduras\DocumentoImpresionHn;
use Luecano\NumeroALetras\NumeroALetras;

final class Ticket80mm
{
    public const VISTA = 'reportes.facturacion.ticket-80mm';

    public const CLAVE = 'imprimir_factura_ticket_80mm';

    /** @var list<string> */
    public const DOCUMENTOS = [
        'Factura',
        'Ticket',
        'Factura con RTN',
        'Factura sin RTN',
    ];

    /** Plantillas propias: el switch no las reemplaza. */
    /** @var list<int> */
    public const EMPRESAS_PROPIAS = [818, 716, 420, 614, 700];

    public static function aplica(Empresa $empresa, Documento $documento): bool
    {
        $nombre = (string) $documento->nombre;
        if (in_array($nombre, [
            DocumentosDefaultPorPais::CR_FACTURA,
            DocumentosDefaultPorPais::CR_TIQUETE,
        ], true)) {
            return false;
        }
        if (in_array((int) $empresa->id, self::EMPRESAS_PROPIAS, true)) {
            return false;
        }
        if (DocumentoImpresionHn::usaTicketAccesorios($empresa, $nombre)) {
            return false;
        }
        if (! (bool) data_get($empresa->custom_empresa, 'configuraciones.'.self::CLAVE, false)) {
            return false;
        }

        return in_array($nombre, self::DOCUMENTOS, true);
    }

    public static function imprimir(Venta $venta, Empresa $empresa, Documento $documento)
    {
        $cliente = $venta->id_cliente
            ? Cliente::withoutGlobalScope('empresa')->find($venta->id_cliente)
            : null;
        $venta->loadMissing('detalles');
        $partes = explode('.', number_format((float) $venta->total, 2, '.', ''));
        $letras = (new NumeroALetras())->toWords((float) $partes[0]);
        $centavos = str_pad($partes[1] ?? '00', 2, '0', STR_PAD_LEFT);
        $datos = compact('venta', 'empresa', 'documento', 'cliente', 'letras', 'centavos');
        $enPdf = (bool) data_get($empresa->custom_empresa, 'configuraciones.ticket_en_pdf', false);

        if (! $enPdf) {
            $venta->pdf = false;

            return view(self::VISTA, $datos);
        }

        $venta->pdf = true;
        $lineas = max(1, $venta->detalles->count());
        $altoPt = (220 + ($lineas * 8)) * 2.83465;
        $pdf = app('dompdf.wrapper')->loadView(self::VISTA, $datos);
        $pdf->setPaper([0, 0, 80 * 2.83465, $altoPt]);

        return $pdf->stream('ticket-80mm.pdf');
    }
}
