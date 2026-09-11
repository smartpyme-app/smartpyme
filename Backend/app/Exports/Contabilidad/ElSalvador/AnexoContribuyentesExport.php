<?php

namespace App\Exports\Contabilidad\ElSalvador;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Http\Request;
use App\Models\Admin\Empresa;

class AnexoContribuyentesExport implements FromCollection, WithMapping, WithCustomCsvSettings
{

    public $request;

    public function filter(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Verifica si la empresa tiene facturación electrónica habilitada
     */
    private function tieneFacturacionElectronica(): bool
    {
        $empresa = Auth::user()->empresa()->first();
        return $empresa && $empresa->facturacion_electronica === true;
    }

    /**
     * Obtiene la clase de documento (DTE o Impreso)
     */
    private function obtenerClaseDocumento($venta): string
    {
        if ($this->tieneFacturacionElectronica() && $venta->sello_mh) {
            return '4'; // DTE
        }
        return '1'; // Impreso
    }

    /**
     * Obtiene el código de generación o correlativo según facturación electrónica
     */
    private function obtenerCodigoGeneracion($venta): string
    {
        if ($this->tieneFacturacionElectronica()) {
            // Para devoluciones
            if (isset($venta->codigo_generacion) && $venta->codigo_generacion) {
                return str_replace('-', '', $venta->codigo_generacion);
            }
            // Para ventas
            if ($venta->sello_mh && isset($venta->dte['identificacion']['codigoGeneracion'])) {
                return str_replace('-', '', $venta->dte['identificacion']['codigoGeneracion']);
            }
            // Para devoluciones con DTE
            $dte = $venta->dte ?? [];
            if (isset($dte['identificacion']['codigoGeneracion'])) {
                return str_replace('-', '', $dte['identificacion']['codigoGeneracion']);
            }
        }
        return trim((string) $venta->correlativo);
    }

    /**
     * Obtiene la clase de documento para devoluciones también
     */
    private function obtenerClaseDocumentoGeneral($item): string
    {
        if ($this->tieneFacturacionElectronica()) {
            // Verificar si es devolución o venta con sello
            if (isset($item->sello_mh) && $item->sello_mh) {
                return '4'; // DTE
            }
            // Para devoluciones con DTE
            $dte = $item->dte ?? [];
            if (!empty($dte)) {
                return '4'; // DTE
            }
        }
        return '1'; // Impreso
    }

    public function collection()
    {
        $request = $this->request;//where('id_empresa', Auth::user()->id_empresa)
        
        $ventas = Venta::with(['cliente', 'documento'])
                        ->where('estado', '!=', 'Anulada')
                        ->whereHas('documento', function($q) {
                            $q->where('nombre', 'Crédito fiscal');
                        })
                        ->when($request->id_sucursal, function ($query) use ($request) {
                            return $query->where('id_sucursal', $request->id_sucursal);
                        })
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->where('cotizacion', 0)
                        ->orderByDesc('fecha')
                        ->get();

        $devoluciones = DevolucionVenta::with(['cliente', 'documento'])
            ->where('enable', true)
            ->whereHas('venta', function ($query) {
                $query->where('estado', '!=', 'Anulada')
                    ->whereHas('documento', function ($q) {
                        $q->where('nombre', 'Crédito fiscal');
                    });
            })
            ->where(function ($query) {
                $query->whereHas('documento', function ($q) {
                    $q->whereIn('nombre', ['Nota de crédito', 'Nota de débito']);
                })->orWhereIn('tipo_dte', ['05', '06']);
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        if ($this->tieneFacturacionElectronica()) {
            $ventas = $ventas->reject(function ($venta) {
                return empty($venta->sello_mh);
            });
            $devoluciones = $devoluciones->reject(function ($devolucion) {
                return empty($devolucion->sello_mh) && empty($devolucion->dte);
            });
        }

        return $this->unirDocumentos($ventas, $devoluciones);
    }

    /**
     * Una fila por documento. No volver a mergear $ventas consigo misma.
     */
    private function unirDocumentos($ventas, $devoluciones)
    {
        return $ventas->merge($devoluciones)->sortBy(function ($item) {
            return [data_get($item, 'fecha'), data_get($item, 'correlativo')];
        });
    }

    public function map($venta): array{
            setlocale(LC_NUMERIC, 'C');

            $dte = $this->dteDe($venta);
            $identificacion = $dte['identificacion'] ?? [];
            $documento = $venta->documento;
            $cliente = optional($venta->cliente);
            $montos = $this->montosDe($venta, $dte);

            $tipo = $identificacion['tipoDte'] ?? $this->tipoDesdeDocumentoLocal($documento);

            $numeroControl = '';
            $sello = '';
            if ($this->tieneFacturacionElectronica()) {
                if (isset($venta->numero_control) && $venta->numero_control) {
                    $numeroControl = str_replace('-', '', $venta->numero_control);
                }
                if (isset($identificacion['numeroControl'])) {
                    $numeroControl = str_replace('-', '', $identificacion['numeroControl']);
                }

                if (isset($dte['sello'])) {
                    $sello = $dte['sello'];
                } elseif (isset($venta->sello_mh) && $venta->sello_mh) {
                    $sello = $venta->sello_mh;
                }
            }

            $tieneFE = $this->tieneFacturacionElectronica() && ($venta->sello_mh || $dte !== []);
            $correlativo = trim((string) $venta->correlativo);
            $codigoDoc = $this->obtenerCodigoGeneracion($venta);
            $fecha = $identificacion['fecEmi'] ?? $venta->fecha;

            $fields = [
                \Carbon\Carbon::parse($fecha)->format('d/m/Y'),
                $this->obtenerClaseDocumentoGeneral($venta),
                $tipo,
                $numeroControl,
                $sello,
                $codigoDoc,
                $tieneFE ? '' : $correlativo,
                $this->nitONrc($cliente, $dte),
                $dte['receptor']['nombre'] ?? $venta->nombre_cliente,
                number_format($montos['exenta'], 2, '.', ''),
                number_format($montos['no_sujeta'], 2, '.', ''),
                number_format($montos['gravada'], 2, '.', ''),
                number_format($montos['iva'], 2, '.', ''),
                number_format($montos['terceros'], 2, '.', ''),
                '0.00',
                number_format($montos['total'], 2, '.', ''),
                '',
                $this->tipoOperacion($venta->tipo_operacion),
                $this->tipoRenta($venta->tipo_renta),
                1,
            ];

        return $fields;
    }

    private function dteDe($item): array
    {
        $dte = $item->dte ?? [];

        return is_array($dte) ? $dte : [];
    }

    private function tipoDesdeDocumentoLocal($documento): string
    {
        if ($documento && $documento->nombre == 'Nota de crédito') {
            return '05';
        }
        if ($documento && $documento->nombre == 'Nota de débito') {
            return '06';
        }

        return '03';
    }

    private function nitONrc($cliente, array $dte): string
    {
        $receptor = $dte['receptor'] ?? [];
        if (!empty($receptor['nit'])) {
            return str_replace('-', '', (string) $receptor['nit']);
        }
        if (!empty($receptor['nrc'])) {
            return str_replace('-', '', (string) $receptor['nrc']);
        }

        return (string) ($cliente->ncr ?? $cliente->nit ?? '');
    }

    private function ivaDesdeResumen(array $resumen): ?float
    {
        foreach ($resumen['tributos'] ?? [] as $tributo) {
            if (($tributo['codigo'] ?? '') === '20') {
                return (float) ($tributo['valor'] ?? 0);
            }
        }

        return null;
    }

    private function montosDe($venta, array $dte): array
    {
        $resumen = $dte['resumen'] ?? [];
        if ($resumen !== []) {
            return [
                'exenta' => (float) ($resumen['totalExenta'] ?? 0),
                'no_sujeta' => (float) ($resumen['totalNoSuj'] ?? 0),
                'gravada' => (float) ($resumen['totalGravada'] ?? 0),
                'iva' => $this->ivaDesdeResumen($resumen) ?? (float) ($venta->iva ?? 0),
                'terceros' => (float) ($resumen['totalNoGravado'] ?? $venta->cuenta_a_terceros ?? 0),
                'total' => (float) ($resumen['montoTotalOperacion'] ?? $resumen['totalPagar'] ?? $venta->total ?? 0),
            ];
        }

        $exenta = (float) ($venta->exenta ?? 0);
        $noSujeta = (float) ($venta->no_sujeta ?? 0);
        $gravada = (float) ($venta->gravada ?? 0);
        if ($gravada == 0.0 && $exenta == 0.0) {
            if ((float) ($venta->iva ?? 0) > 0) {
                $gravada = (float) ($venta->sub_total ?? 0);
            } else {
                $exenta = (float) ($venta->sub_total ?? 0);
            }
        }

        return [
            'exenta' => $exenta,
            'no_sujeta' => $noSujeta,
            'gravada' => $gravada,
            'iva' => (float) ($venta->iva ?? 0),
            'terceros' => (float) ($venta->cuenta_a_terceros ?? 0),
            'total' => (float) ($venta->total ?? 0),
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

    function tipoOperacion($operacion) {
        switch ($operacion) {
            case 'Gravada': return 1;
            case 'No Gravada': return 2;
            case 'Excluido': return 3;
            case 'Mixta': return 4;
            default: return '0';
        }
    }

    function tipoRenta($tipo) {
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
            default: return '0';
        }
    }

}