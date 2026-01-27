<?php

namespace App\Services\FacturacionElectronica\Implementations\ElSalvador;

use App\Services\FacturacionElectronica\Implementations\ElSalvador\ElSalvadorFE;
use App\Models\MH\Unidad;
use App\Models\Admin\Empresa;
use Ramsey\Uuid\Uuid;
use Luecano\NumeroALetras\NumeroALetras;
use Carbon\Carbon;

/**
 * Implementación de Comprobante de Crédito Fiscal (CCF) para El Salvador
 * 
 * Genera documentos tipo 03 (Comprobante de Crédito Fiscal) según especificaciones de MH El Salvador
 * 
 * @package App\Services\FacturacionElectronica\Implementations\ElSalvador
 */
class ElSalvadorCCF extends ElSalvadorFE
{
    protected $venta;
    protected $sucursal;
    protected $caja_codigo;

    /**
     * Genera el DTE de tipo Comprobante de Crédito Fiscal (03)
     * 
     * @param mixed $documento Venta
     * @return array Estructura del DTE
     */
    public function generarDTE($documento): array
    {
        $this->venta = $documento;
        $this->empresa = $this->venta->empresa()->first();
        $this->sucursal = $this->venta->sucursal()->first();
        
        $this->setEmpresa($this->empresa);

        $this->caja_codigo = $this->sucursal->codigo_punto_venta ?? 'P001';
        $this->venta->tipo_dte = '03';
        $this->venta->numero_control = 'DTE-' . $this->venta->tipo_dte . '-' . $this->sucursal->cod_estable_mh . $this->caja_codigo . '-' . str_pad($this->venta->correlativo, 15, '0', STR_PAD_LEFT);

        if (!$this->venta->codigo_generacion) {
            $this->venta->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
        }
        $this->venta->save();

        $this->venta->ambiente = $this->empresa->fe_ambiente ?? '00';
        $this->venta->tipoModelo = 1;
        $this->venta->tipoOperacion = 1;
        $this->venta->tipoContingencia = NULL;
        $this->venta->motivoContin = NULL;
        $this->venta->moneda = 'USD';
        $this->venta->version = 3;

        // Condición
        if ($this->venta->condicion == 'Crédito') {
            $this->venta->cod_condicion = 2; // Crédito
        } else {
            $this->venta->cod_condicion = 1; // Contado
        }

        // Método de pago
        switch ($this->venta->forma_pago) {
            case 'Efectivo':
                $this->venta->cod_metodo_pago = '01';
                break;
            case 'Tarjeta de crédito/débito':
                $this->venta->cod_metodo_pago = '02';
                break;
            case 'Cheque':
                $this->venta->cod_metodo_pago = '04';
                break;
            case 'Transferencia':
                $this->venta->cod_metodo_pago = '05';
                break;
            case 'Vales':
                $this->venta->cod_metodo_pago = '06';
                break;
            case 'Chivo Wallet':
                $this->venta->cod_metodo_pago = '09';
                break;
            case 'Bitcoin':
                $this->venta->cod_metodo_pago = '11';
                break;
            default:
                $this->venta->cod_metodo_pago = '01';
                break;
        }

        // Total en letras
        $formatter = new NumeroALetras();
        $n = explode(".", number_format($this->venta->total, 2));
        
        $dolares = $formatter->toWords(floatval(str_replace(',', '', $n[0])));
        $centavos = $formatter->toWords($n[1]);

        $this->venta->total_en_letras = $dolares . ' DÓLARES CON ' . $centavos . ' CENTAVOS.';
        
        return $this->generarCCF();
    }

    /**
     * Genera la estructura completa del CCF
     * 
     * @return array
     */
    protected function generarCCF(): array
    {
        $tributos = NULL;
        $apendice = NULL;
        
        if ($this->venta->observaciones) {
            $apendice = collect();
            $apendice->push(['etiqueta' => 'Observaciones', 'campo' => 'Observaciones', 'valor' => $this->venta->observaciones]);
        }

        if ($this->venta->iva > 0) {
            $tributos = collect();
            if ($this->venta->iva) {
                $tributos->push(['codigo' => '20', 'descripcion' => 'Impuesto al Valor Agregado 13%', 'valor' => floatval(number_format($this->venta->iva, 2, '.', ''))]);
            }
            $this->venta->gravada = $this->venta->sub_total;
        } else {
            $this->venta->gravada = 0;
            $this->venta->exenta = $this->venta->sub_total;
        }

        return [
            "identificacion" => $this->identificador(),
            "documentoRelacionado" => NULL,
            "emisor" => $this->emisor(),
            "receptor" => $this->receptor(),
            "otrosDocumentos" => NULL,
            "ventaTercero" => NULL,
            "cuerpoDocumento" => $this->detalles(),
            "resumen" => [
                "totalNoSuj" => floatval(number_format($this->venta->no_sujeta, 2, '.', '')),
                "totalExenta" => floatval(number_format($this->venta->exenta, 2, '.', '')),
                "totalGravada" => floatval(number_format($this->venta->gravada, 2, '.', '')),
                "subTotalVentas" => floatval(number_format($this->venta->sub_total, 2, '.', '')),
                "descuNoSuj" => 0,
                "descuExenta" => 0,
                "descuGravada" => floatval(number_format(0, 2, '.', '')),
                "porcentajeDescuento" => 0,
                "totalDescu" => floatval(number_format(0, 2, '.', '')),
                "tributos" => $tributos,
                "subTotal" => floatval(number_format($this->venta->sub_total, 2, '.', '')),
                "ivaPerci1" => floatval(number_format($this->venta->iva_percibido, 2, '.', '')),
                "ivaRete1" => floatval(number_format($this->venta->iva_retenido, 2, '.', '')),
                "reteRenta" => floatval(number_format($this->venta->renta_retenida ?? 0, 2, '.', '')),
                "montoTotalOperacion" => floatval(number_format($this->venta->total - $this->venta->cuenta_a_terceros + $this->venta->iva_retenido + $this->venta->renta_retenida, 2, '.', '')),
                "totalNoGravado" => floatval(number_format($this->venta->cuenta_a_terceros, 2, '.', '')),
                "totalPagar" => floatval(number_format($this->venta->total, 2, '.', '')),
                "totalLetras" => $this->venta->total_en_letras,
                "saldoFavor" => 0,
                "condicionOperacion" => $this->venta->cod_condicion,
                "pagos" => [
                    [
                        "codigo" => $this->venta->cod_metodo_pago,
                        "montoPago" => floatval(number_format($this->venta->total, 2, '.', '')),
                        "referencia" => NULL,
                        "plazo" => $this->venta->cod_condicion == 2 ? $this->obtenerPlazo($this->venta->dias_credito) : NULL,
                        "periodo" => $this->venta->cod_condicion == 2 ? Carbon::parse($this->venta->fecha)->diffInDays(Carbon::parse($this->venta->fecha_pago), false) : NULL
                    ]
                ],
                "numPagoElectronico" => ""
            ],
            "extension" => NULL,
            "apendice" => $apendice
        ];
    }

    /**
     * Genera el identificador del DTE
     * 
     * @return array
     */
    protected function identificador(): array
    {
        return [
            "version" => $this->venta->version,
            "ambiente" => $this->venta->ambiente,
            "tipoDte" => $this->venta->tipo_dte,
            "numeroControl" => $this->venta->numero_control,
            "codigoGeneracion" => $this->venta->codigo_generacion,
            "tipoModelo" => $this->venta->tipoModelo,
            "tipoOperacion" => $this->venta->tipoOperacion,
            "tipoContingencia" => $this->venta->tipoContingencia,
            "motivoContin" => $this->venta->motivoContin,
            "fecEmi" => Carbon::parse($this->venta->fecha)->format('Y-m-d'),
            "horEmi" => Carbon::parse($this->venta->created_at)->format('H:i:s'),
            "tipoMoneda" => $this->venta->moneda,
        ];
    }

    /**
     * Genera los datos del emisor
     * 
     * @return array
     */
    protected function emisor(): array
    {
        return [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nrc" => str_replace('-', '', $this->empresa->ncr),
            "nombre" => $this->empresa->nombre,
            "codActividad" => $this->empresa->cod_actividad_economica,
            "descActividad" => $this->empresa->giro,
            "nombreComercial" => $this->empresa->nombre_comercial,
            "tipoEstablecimiento" => $this->sucursal->tipo_establecimiento,
            "direccion" => [
                "departamento" => $this->empresa->cod_departamento,
                "municipio" => $this->empresa->cod_municipio,
                "complemento" => $this->empresa->direccion,
            ],
            "telefono" => $this->empresa->telefono,
            "codEstableMH" => $this->sucursal->cod_estable_mh ? $this->sucursal->cod_estable_mh : NULL,
            "codEstable" => $this->empresa->cod_estable ? $this->empresa->cod_estable : NULL,
            "codPuntoVentaMH" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "codPuntoVenta" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "correo" => $this->empresa->correo,
        ];
    }

    /**
     * Genera los datos del receptor (CCF tiene estructura diferente)
     * 
     * @return array
     */
    protected function receptor(): array
    {
        return [
            "nit" => $this->venta->cliente->nit ? str_replace('-', '', $this->venta->cliente->nit) : str_replace('-', '', $this->venta->cliente->dui),
            "nombreComercial" => $this->venta->cliente->nombre_empresa,
            "nrc" => str_replace('-', '', $this->venta->cliente->ncr),
            "nombre" => $this->venta->nombre_cliente,
            "codActividad" => $this->venta->cliente->cod_giro,
            "descActividad" => $this->venta->cliente->giro,
            "direccion" => [
                "departamento" => $this->venta->cliente->cod_departamento,
                "municipio" => $this->venta->cliente->cod_departamento, // Nota: En CCF original usa cod_departamento para municipio
                "complemento" => $this->venta->cliente->empresa_direccion ?? $this->venta->cliente->direccion,
            ],
            "telefono" => $this->venta->cliente->telefono,
            "correo" => $this->venta->cliente->correo
        ];
    }

    /**
     * Genera los detalles de productos/servicios
     * 
     * @return \Illuminate\Support\Collection
     */
    protected function detalles()
    {
        $detalles = collect();

        if ($this->venta->descripcion_personalizada) {
            if ($this->venta->detalles()->first()->producto()->pluck('tipo')->first() == 'Servicio') {
                $this->venta->tipo_item = 2;
            } else {
                $this->venta->tipo_item = 1;
            }

            if ($this->venta->iva > 0) {
                $this->venta->gravada = $this->venta->sub_total;
            } else {
                $this->venta->gravada = 0;
                $this->venta->exenta = $this->venta->sub_total;
            }

            $tributos = NULL;
            if ($this->venta->iva > 0) {
                $tributos = ['20'];
                $this->venta->gravada = $this->venta->detalles()->sum('total');
            } else {
                $this->venta->gravada = 0;
                $this->venta->exenta = $this->venta->detalles()->sum('total');
            }

            $detalles->push([
                "numItem" => 1,
                "tipoItem" => $this->venta->tipo_item,
                "numeroDocumento" => NULL,
                "cantidad" => floatval(number_format(1, 2, '.', '')),
                "codigo" => NULL,
                "codTributo" => NULL,
                "uniMedida" => 59,
                "descripcion" => $this->venta->descripcion_impresion,
                "precioUni" => floatval(number_format($this->venta->sub_total, 2, '.', '')),
                "montoDescu" => floatval(number_format($this->venta->descuento, 2, '.', '')),
                "ventaNoSuj" => floatval(number_format($this->venta->no_sujeta, 2, '.', '')),
                "ventaExenta" => floatval(number_format($this->venta->exenta, 2, '.', '')),
                "ventaGravada" => floatval(number_format($this->venta->gravada, 2, '.', '')),
                "tributos" => $tributos,
                "psv" => 0,
                "noGravado" => 0,
            ]);

            return $detalles;
        }

        foreach ($this->venta->detalles as $index => $detalle) {
            $cod = Unidad::where('nombre', ucfirst($detalle->unidad))->pluck('cod')->first();
            if ($cod) {
                $detalle->cod_medida = $cod;
            } else {
                $detalle->cod_medida = 59;
            }

            // Tipo Item
            if ($detalle->producto()->pluck('tipo')->first() == 'Servicio') {
                $detalle->tipo_item = 2;
            } else {
                $detalle->tipo_item = 1;
            }

            $tributos = NULL;
            $detalle->codTributo = NULL;
            
            if ($detalle->producto) {
                $detalle->codigo = $detalle->producto->codigo;
            } else {
                $detalle->codigo = null;
            }

            if ($this->venta->iva > 0) {
                $tributos = ['20'];
                $detalle->gravada = $detalle->total;
            } else {
                $detalle->gravada = 0;
                $detalle->exenta = $detalle->total;
            }

            // Producto no Gravado
            if ($detalle->cuenta_a_terceros > 0) {
                $detalles->push([
                    "numItem" => count($detalles) + 1,
                    "tipoItem" => $detalle->tipo_item,
                    "numeroDocumento" => NULL,
                    "cantidad" => floatval(number_format($detalle->cantidad, 2, '.', '')),
                    "codigo" => $detalle->codigo,
                    "codTributo" => $detalle->codTributo,
                    "uniMedida" => $detalle->cod_medida,
                    "descripcion" => $detalle->nombre_producto,
                    "precioUni" => floatval(number_format($detalle->precio, 4, '.', '')),
                    "montoDescu" => floatval(number_format($detalle->descuento, 2, '.', '')),
                    "ventaNoSuj" => floatval(number_format($detalle->no_sujeta, 2, '.', '')),
                    "ventaExenta" => floatval(number_format($detalle->exenta, 2, '.', '')),
                    "ventaGravada" => floatval(number_format($detalle->gravada, 2, '.', '')),
                    "tributos" => $tributos,
                    "psv" => 0,
                    "noGravado" => 0,
                ]);
                
                $detalles->push([
                    "numItem" => count($detalles) + 1,
                    "tipoItem" => 2,
                    "numeroDocumento" => NULL,
                    "cantidad" => 1,
                    "codigo" => null,
                    "codTributo" => null,
                    "uniMedida" => 99,
                    "descripcion" => 'Cobro por cuenta a terceros',
                    "precioUni" => 0,
                    "montoDescu" => 0,
                    "ventaNoSuj" => 0,
                    "ventaExenta" => 0,
                    "ventaGravada" => 0,
                    "tributos" => NULL,
                    "psv" => 0,
                    "noGravado" => floatval(number_format($detalle->cuenta_a_terceros, 2, '.', '')),
                ]);
            } else {
                $detalles->push([
                    "numItem" => count($detalles) + 1,
                    "tipoItem" => $detalle->tipo_item,
                    "numeroDocumento" => NULL,
                    "cantidad" => floatval(number_format($detalle->cantidad, 2, '.', '')),
                    "codigo" => $detalle->codigo,
                    "codTributo" => $detalle->codTributo,
                    "uniMedida" => $detalle->cod_medida,
                    "descripcion" => $detalle->nombre_producto,
                    "precioUni" => floatval(number_format($detalle->precio, 4, '.', '')),
                    "montoDescu" => floatval(number_format($detalle->descuento, 2, '.', '')),
                    "ventaNoSuj" => floatval(number_format($detalle->no_sujeta, 2, '.', '')),
                    "ventaExenta" => floatval(number_format($detalle->exenta, 2, '.', '')),
                    "ventaGravada" => floatval(number_format($detalle->gravada, 2, '.', '')),
                    "tributos" => $tributos,
                    "psv" => 0,
                    "noGravado" => 0,
                ]);
            }
        }

        return $detalles;
    }

    /**
     * Obtiene el plazo según días de crédito
     * 
     * @param int $dias_credito
     * @return string
     */
    private function obtenerPlazo($dias_credito): string
    {
        if ($dias_credito <= 30) {
            return "01"; // Corto plazo
        } elseif ($dias_credito <= 60) {
            return "02"; // Mediano plazo
        } else {
            return "03"; // Largo plazo
        }
    }

    /**
     * Genera el documento de anulación
     * 
     * @param array $dte Documento original
     * @param mixed $documento Documento original
     * @return array Documento de anulación
     */
    protected function generarDTEAnulado(array $dte, $documento): array
    {
        $this->venta = $documento;
        $this->empresa = $this->venta->empresa()->first();
        $this->sucursal = $this->venta->sucursal()->first();
        
        $codigoGeneracion = strtoupper(Uuid::uuid4()->toString());
        $this->caja_codigo = $this->sucursal->codigo_punto_venta ?? 'P001';

        $identificacion = [
            "version" => 2,
            "ambiente" => $dte['identificacion']['ambiente'],
            "codigoGeneracion" => $codigoGeneracion,
            "fecAnula" => $this->venta->fecha_anulacion 
                ? Carbon::parse($this->venta->fecha_anulacion)->format('Y-m-d')
                : Carbon::now()->format('Y-m-d'),
            "horAnula" => Carbon::now()->format('H:i:s'),
        ];

        // Para CCF, el receptor tiene estructura diferente
        $tipo_documento = '36';
        $num_documento = $dte['receptor']['nit'] ?? null;
        $nombre = $dte['receptor']['nombre'] ?? null;
        $correo = $dte['receptor']['correo'] ?? null;
        $telefono = $dte['receptor']['telefono'] ?? null;

        $documentoAnular = [
            "tipoDte" => $dte['identificacion']['tipoDte'],
            "codigoGeneracion" => $dte['identificacion']['codigoGeneracion'],
            "selloRecibido" => $dte['sello'] ?? $dte['selloRecibido'] ?? null,
            "numeroControl" => $dte['identificacion']['numeroControl'],
            "fecEmi" => $dte['identificacion']['fecEmi'],
            "montoIva" => isset($dte['resumen']['totalIva']) ? $dte['resumen']['totalIva'] : NULL,
            "codigoGeneracionR" => ($this->venta->tipo_anulacion == 1 || $this->venta->tipo_anulacion == 3) && $this->venta->codigo_generacion_remplazo 
                ? $this->venta->codigo_generacion_remplazo 
                : NULL,
            "tipoDocumento" => $tipo_documento,
            "numDocumento" => $num_documento,
            "nombre" => $nombre,
            "correo" => $correo,
            "telefono" => $telefono,
        ];

        $tipoAnulacion = $this->venta->tipo_anulacion ?? 2;
        $motivoTexto = $this->venta->motivo_anulacion ?? 'Se rescinde la operación.';

        $motivo = [
            "tipoAnulacion" => $tipoAnulacion,
            "motivoAnulacion" => $motivoTexto,
            "nombreResponsable" => $dte['emisor']['nombre'],
            "tipDocResponsable" => '36',
            "numDocResponsable" => $dte['emisor']['nit'],
            "nombreSolicita" => $dte['emisor']['nombre'],
            "tipDocSolicita" => '36',
            "numDocSolicita" => $dte['emisor']['nit'],
        ];

        $emisor = [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nombre" => $this->empresa->nombre,
            "tipoEstablecimiento" => $this->sucursal->tipo_establecimiento,
            "nomEstablecimiento" => $this->empresa->nombre_comercial,
            "codEstable" => $this->sucursal->cod_estable_mh ? $this->sucursal->cod_estable_mh : NULL,
            "codPuntoVenta" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "telefono" => $this->empresa->telefono,
            "correo" => $this->empresa->correo,
        ];

        return [
            "identificacion" => $identificacion,
            "emisor" => $emisor,
            "documento" => $documentoAnular,
            "motivo" => $motivo,
        ];
    }
}
