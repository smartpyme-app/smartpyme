<?php

namespace App\Exports\Contabilidad\ElSalvador;

use App\Models\Admin\Empresa;
use App\Services\Contabilidad\LibroConsumidoresFilas;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;

class AnexoConsumidoresExport implements FromCollection, WithMapping, WithCustomCsvSettings
{
    public $request;

    private ?bool $facturacionElectronica = null;

    private ?Empresa $empresa = null;

    public function filter(Request $request)
    {
        $this->request = $request;
        $this->empresa = Auth::user()->empresa()->first();
    }

    private function feActiva(): bool
    {
        if ($this->facturacionElectronica === null) {
            $this->facturacionElectronica = $this->empresa
                && $this->empresa->facturacion_electronica === true;
        }

        return $this->facturacionElectronica;
    }

    private function obtenerClaseDocumento($venta): string
    {
        if ($this->feActiva() && ! empty($venta->sello_mh)) {
            return '4';
        }

        return '1';
    }

    public function collection()
    {
        $fe = $this->feActiva();

        return LibroConsumidoresFilas::consultaAnexo($this->request, $fe)
            ->with([
                'detalles' => fn ($q) => $q->select('id', 'id_venta', 'id_producto')->orderBy('id')->limit(1),
                'detalles.producto:id,tipo',
            ])
            ->get();
    }

    public function map($venta): array
    {
        setlocale(LC_NUMERIC, 'C');

        $nombreDoc = trim((string) ($venta->documento_nombre ?? ''));
        $esFacturaExportacion = strtolower($nombreDoc) === 'factura de exportación';
        $montos = LibroConsumidoresFilas::montosAnexoConsumidor($venta, $esFacturaExportacion);

        $tieneFE = $this->feActiva() && ! empty($venta->sello_mh);
        $codigoGeneracion = $tieneFE
            ? LibroConsumidoresFilas::codigoAnexoSinGuiones($venta->codigo_generacion ?? '')
            : '';
        $numeroControl = $tieneFE
            ? LibroConsumidoresFilas::codigoAnexoSinGuiones($venta->numero_control ?? '')
            : '';
        $sello = $tieneFE ? (string) ($venta->sello_mh ?? '') : '';
        $correlativo = trim((string) $venta->correlativo);

        return [
            \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y'),
            $this->obtenerClaseDocumento($venta),
            '01',
            $numeroControl,
            $sello,
            $tieneFE ? $codigoGeneracion : $correlativo,
            $tieneFE ? $codigoGeneracion : $correlativo,
            $tieneFE ? '' : $correlativo,
            $tieneFE ? '' : $correlativo,
            null,
            $montos['exenta'] > 0 ? number_format($montos['exenta'], 2, '.', '') : '0.00',
            '0.00',
            $montos['no_sujeta'] > 0 ? number_format($montos['no_sujeta'], 2, '.', '') : '0.00',
            $esFacturaExportacion ? '0.00' : number_format($montos['gravada'], 2, '.', ''),
            $esFacturaExportacion ? number_format($montos['exportacion'], 2, '.', '') : '0.00',
            '0.00',
            '0.00',
            '0.00',
            '0.00',
            number_format($montos['total_propio'], 2, '.', ''),
            $this->tipoOperacion($venta->tipo_operacion),
            $this->tipoRentaVenta($venta),
            2,
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'enclosure' => '',
            'use_bom' => false,
        ];
    }

    function tipoOperacion($operacion)
    {
        switch ($operacion) {
            case 'Gravada': return 1;
            case 'No Gravada': return 2;
            case 'Excluido': return 3;
            case 'Mixta': return 4;
            default: return '0';
        }
    }

    private function tipoRentaVenta($venta)
    {
        $codigo = $this->tipoRenta($venta->tipo_renta);
        if ($codigo !== null) {
            return $codigo;
        }

        return $this->tipoRenta($this->tipoRentaEmpresaFallback($venta));
    }

    private function tipoRentaEmpresaFallback($venta): ?string
    {
        $empresa = $this->empresa;
        if (! $empresa) {
            return null;
        }

        $detalles = $venta->detalles;
        $detalle = $detalles ? $detalles->sortBy('id')->first() : null;
        $producto = $detalle ? $detalle->producto : null;
        if ($producto && $producto->tipo === 'Servicio') {
            return $empresa->tipo_renta_servicios;
        }

        return $empresa->tipo_renta_productos;
    }

    function tipoRenta($tipo)
    {
        switch ($tipo) {
            case 'Profesiones, Artes y Oficios': return 1;
            case 'Actividades de Servicios': return 2;
            case 'Actividades Comerciales': return 3;
            case 'Actividades Industriales': return 4;
            case 'Actividades Agropecuarias': return 5;
            case 'Utilidades y Dividendos': return 6;
            case 'Exportaciones de bienes': return 7;
            case 'Servicios Realizados en el Exterior y Utilizados en El Salvador': return 8;
            case 'Exportaciones de servicios': return 9;
            case 'Otras Rentas Gravables': return 10;
            case 'Ingresos que ya fueron sujetos de retención informados en el F14 y consolidados en F910': return 12;
            case 'Sujetos pasivos excluidos': return 13;
            default: return null;
        }
    }
}
