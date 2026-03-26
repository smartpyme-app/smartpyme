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
                        ->when($request->tipo_documento, function($query) {
                            return $query->whereHas('documento', function($q) {
                                $q->where('nombre', 'Crédito fiscal');
                            });
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
                $query->where('estado', '!=', 'Anulada');
            })
            ->when($request->id_sucursal, function ($query) use ($request) {
                return $query->where('id_sucursal', $request->id_sucursal);
            })
            ->whereBetween('fecha', [$request->inicio, $request->fin])
            ->get();

        $libroVentas = $ventas->merge($ventas)->merge($devoluciones)->sortBy(function ($item) {
                return [$item['fecha'], $item['correlativo']];
            });

        return $libroVentas;
        
    }

    public function map($venta): array{
            setlocale(LC_NUMERIC, 'C');
            
            $documento = $venta->documento;
            $cliente = optional($venta->cliente);

            $tipo = '03'; //CCF

            if ($documento && $documento->nombre == 'Nota de crédito') {
                $tipo = '05';
            }

            if ($documento && $documento->nombre == 'Nota de débito') {
                $tipo = '06';
            }

            if ($venta->iva > 0) {
                $venta->gravada = $venta->sub_total;
            }else{
                $venta->gravada = 0;
                $venta->exenta = $venta->sub_total;
            }

            // Obtener número de control y sello según facturación electrónica
            $numeroControl = '';
            $sello = '';
            if ($this->tieneFacturacionElectronica()) {
                // Para devoluciones
                if (isset($venta->numero_control) && $venta->numero_control) {
                    $numeroControl = str_replace('-', '', $venta->numero_control);
                }
                // Para ventas
                if ($venta->sello_mh && isset($venta->dte['identificacion']['numeroControl'])) {
                    $numeroControl = str_replace('-', '', $venta->dte['identificacion']['numeroControl']);
                }
                // Para devoluciones con DTE
                $dte = $venta->dte ?? [];
                if (isset($dte['identificacion']['numeroControl'])) {
                    $numeroControl = str_replace('-', '', $dte['identificacion']['numeroControl']);
                }
                
                // Obtener sello
                if (isset($venta->dte['sello'])) {
                    $sello = $venta->dte['sello'];
                } elseif (isset($venta->sello_mh) && $venta->sello_mh) {
                    $sello = $venta->sello_mh;
                }
            }

            // Según guía de Hacienda:
            // Para documentos IMPRESOS (sin FE): F = correlativo, G = correlativo
            // Para documentos DTE (con FE): F = código generación, G = vacío
            $tieneFE = $this->tieneFacturacionElectronica() && ($venta->sello_mh || !empty($venta->dte ?? []));
            $correlativo = trim($venta->correlativo);
            $codigoDoc = $this->obtenerCodigoGeneracion($venta);
            
            $fields = [
                \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y'), //A Fecha sin ceros a la izquierda
                $this->obtenerClaseDocumentoGeneral($venta), //B Clase DTE o Impreso
                $tipo, //C Tipo
                $numeroControl, //D Num Resolución (vacío si impreso)
                $sello, //E Num Serie (vacío si impreso)
                $codigoDoc, //F Num Documento (código generación si DTE, correlativo si impreso)
                $tieneFE ? '' : $correlativo, //G Número Control Interno (vacío si DTE, correlativo si impreso)
                $cliente->ncr ?? $cliente->nit, //H NIT/NRC
                isset($venta->dte['receptor']) ? $venta->dte['receptor']['nombre'] : $venta->nombre_cliente, //I Nombre
                number_format($venta->exenta, 2, '.', ''), //J Exentas (formato numérico con 2 decimales)
                number_format($venta->no_sujeta, 2, '.', ''), //K No sujetas (formato numérico con 2 decimales)
                number_format($venta->gravada, 2, '.', ''), //L Gravadas (formato numérico con 2 decimales)
                number_format($venta->iva, 2, '.', ''), //M Debido fiscal (formato numérico con 2 decimales)
                '0.00', //N Ventas a terceros
                '0.00', //O Débito ventas a terceros
                number_format($venta->total, 2, '.', ''), //P Total (formato numérico con 2 decimales)
                '', //Q DUI (vacío)
                $this->tipoOperacion($venta->tipo_operacion), //R Tipo operación renta 1 Gravada 2 Exenta
                $this->tipoRenta($venta->tipo_renta), //S Tipo ingreso renta
                1, //T Número de Anexo
            ];

        return $fields;
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
