<?php

namespace App\Services\FacturacionElectronica\Implementations\ElSalvador;

use App\Services\FacturacionElectronica\Implementations\ElSalvador\ElSalvadorFE;
use App\Models\MH\Unidad;
use App\Models\Admin\Empresa;
use Ramsey\Uuid\Uuid;
use Luecano\NumeroALetras\NumeroALetras;
use Carbon\Carbon;

/**
 * Implementación de Nota de Débito para El Salvador
 * 
 * Genera documentos tipo 06 (Nota de Débito) según especificaciones de MH El Salvador
 * 
 * @package App\Services\FacturacionElectronica\Implementations\ElSalvador
 */
class ElSalvadorNotaDebito extends ElSalvadorFE
{
    protected $devolucion;
    protected $sucursal;
    protected $caja_codigo;

    /**
     * Genera el DTE de tipo Nota de Débito (06)
     * 
     * @param mixed $documento Devolucion
     * @return array Estructura del DTE
     */
    public function generarDTE($documento): array
    {
        $this->devolucion = $documento;
        $this->empresa = $this->devolucion->empresa()->first();
        $this->sucursal = $this->devolucion->usuario()->first()->sucursal()->first();
        
        $this->setEmpresa($this->empresa);

        $this->caja_codigo = $this->sucursal->codigo_punto_venta ?? 'P001';
        $this->devolucion->tipo_dte = '06';
        $this->devolucion->numero_control = 'DTE-' . $this->devolucion->tipo_dte . '-' . $this->sucursal->cod_estable_mh . $this->caja_codigo . '-' . str_pad($this->devolucion->correlativo, 15, '0', STR_PAD_LEFT);

        if (!$this->devolucion->codigo_generacion) {
            $this->devolucion->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
        }
        $this->devolucion->save();

        $this->devolucion->ambiente = $this->empresa->fe_ambiente ?? '00';
        $this->devolucion->tipoModelo = 1;
        $this->devolucion->tipoOperacion = 1;
        $this->devolucion->tipoContingencia = NULL;
        $this->devolucion->motivoContin = NULL;
        $this->devolucion->moneda = 'USD';
        $this->devolucion->version = 3;

        // Condición
        if ($this->devolucion->condicion == 'Crédito') {
            $this->devolucion->cod_condicion = 2;
        } else {
            $this->devolucion->cod_condicion = 1;
        }

        // Método de pago
        switch ($this->devolucion->forma_pago) {
            case 'Efectivo':
                $this->devolucion->cod_metodo_pago = '01';
                break;
            case 'Tarjeta de crédito/débito':
                $this->devolucion->cod_metodo_pago = '02';
                break;
            case 'Cheque':
                $this->devolucion->cod_metodo_pago = '04';
                break;
            case 'Transferencia':
                $this->devolucion->cod_metodo_pago = '05';
                break;
            case 'Vales':
                $this->devolucion->cod_metodo_pago = '06';
                break;
            case 'Chivo Wallet':
                $this->devolucion->cod_metodo_pago = '09';
                break;
            case 'Bitcoin':
                $this->devolucion->cod_metodo_pago = '11';
                break;
            default:
                $this->devolucion->cod_metodo_pago = '01';
                break;
        }

        // Total en letras
        $formatter = new NumeroALetras();
        $n = explode(".", number_format($this->devolucion->total, 2));
        
        $dolares = $formatter->toWords(floatval(str_replace(',', '', $n[0])));
        $centavos = $formatter->toWords($n[1]);

        $this->devolucion->total_en_letras = $dolares . ' DÓLARES CON ' . $centavos . ' CENTAVOS.';
        
        return $this->generarNotaDebito();
    }

    /**
     * Genera la estructura completa de la nota de débito
     * 
     * @return array
     */
    protected function generarNotaDebito(): array
    {
        $tributos = NULL;

        if ($this->devolucion->iva > 0) {
            $tributos = collect();
            if ($this->devolucion->iva) {
                $tributos->push(['codigo' => '20', 'descripcion' => 'Impuesto al Valor Agregado 13%', 'valor' => floatval(number_format($this->devolucion->iva, 2, '.', ''))]);
            }
            $this->devolucion->gravada = $this->devolucion->sub_total;
        } else {
            $this->devolucion->gravada = 0;
            $this->devolucion->exenta = $this->devolucion->sub_total;
        }

        return [
            "identificacion" => $this->identificador(),
            "documentoRelacionado" => [$this->documentoRelacionado()],
            "emisor" => $this->emisor(),
            "receptor" => $this->receptor(),
            "ventaTercero" => NULL,
            "cuerpoDocumento" => $this->detalles(),
            "resumen" => [
                "totalNoSuj" => floatval(number_format($this->devolucion->no_sujeta, 2, '.', '')),
                "totalExenta" => floatval(number_format($this->devolucion->exenta, 2, '.', '')),
                "totalGravada" => floatval(number_format($this->devolucion->gravada, 2, '.', '')),
                "subTotalVentas" => floatval(number_format($this->devolucion->sub_total, 2, '.', '')),
                "descuNoSuj" => 0,
                "descuExenta" => 0,
                "descuGravada" => floatval(number_format($this->devolucion->descuento, 2, '.', '')),
                "totalDescu" => floatval(number_format($this->devolucion->descuento, 2, '.', '')),
                "tributos" => $tributos,
                "subTotal" => floatval(number_format($this->devolucion->sub_total, 2, '.', '')),
                "ivaPerci1" => floatval(number_format($this->devolucion->iva_percibido, 2, '.', '')),
                "ivaRete1" => floatval(number_format($this->devolucion->iva_retenido, 2, '.', '')),
                "reteRenta" => 0,
                "montoTotalOperacion" => floatval(number_format($this->devolucion->total - $this->devolucion->cuenta_a_terceros + $this->devolucion->iva_retenido, 2, '.', '')),
                "totalLetras" => $this->devolucion->total_en_letras,
                "condicionOperacion" => $this->devolucion->cod_condicion,
                "numPagoElectronico" => '',
            ],
            "extension" => NULL,
            "apendice" => [
                [
                    "campo" => "empleado",
                    "etiqueta" => "nombre",
                    "valor" => $this->devolucion->nombre_usuario
                ]
            ]
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
            "version" => $this->devolucion->version,
            "ambiente" => $this->devolucion->ambiente,
            "tipoDte" => $this->devolucion->tipo_dte,
            "numeroControl" => $this->devolucion->numero_control,
            "codigoGeneracion" => $this->devolucion->codigo_generacion,
            "tipoModelo" => $this->devolucion->tipoModelo,
            "tipoOperacion" => $this->devolucion->tipoOperacion,
            "tipoContingencia" => $this->devolucion->tipoContingencia,
            "motivoContin" => $this->devolucion->motivoContin,
            "fecEmi" => Carbon::parse($this->devolucion->fecha)->format('Y-m-d'),
            "horEmi" => Carbon::parse($this->devolucion->created_at)->format('H:i:s'),
            "tipoMoneda" => $this->devolucion->moneda,
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
            "correo" => $this->empresa->correo,
        ];
    }

    /**
     * Genera los datos del receptor
     * 
     * @return array
     */
    protected function receptor(): array
    {
        return [
            "nit" => $this->devolucion->cliente->nit ? str_replace('-', '', $this->devolucion->cliente->nit) : NULL,
            "nombreComercial" => $this->devolucion->cliente->nombre_empresa,
            "nrc" => str_replace('-', '', $this->devolucion->cliente->ncr),
            "nombre" => $this->devolucion->nombre_cliente,
            "codActividad" => $this->devolucion->cliente->cod_giro,
            "descActividad" => $this->devolucion->cliente->giro,
            "direccion" => [
                "departamento" => $this->devolucion->cliente->cod_departamento,
                "municipio" => $this->devolucion->cliente->cod_departamento,
                "complemento" => $this->devolucion->cliente->direccion ? $this->devolucion->cliente->direccion : $this->devolucion->cliente->empresa_direccion,
            ],
            "telefono" => $this->devolucion->cliente->telefono,
            "correo" => $this->devolucion->cliente->correo
        ];
    }

    /**
     * Genera el documento relacionado (la factura original)
     * 
     * @return array
     */
    protected function documentoRelacionado(): array
    {
        return [
            "tipoDocumento" => $this->devolucion->venta->dte['identificacion']['tipoDte'],
            "tipoGeneracion" => 2,
            "numeroDocumento" => $this->devolucion->venta->dte['identificacion']['codigoGeneracion'],
            "fechaEmision" => $this->devolucion->venta->dte['identificacion']['fecEmi'],
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

        foreach ($this->devolucion->detalles as $index => $detalle) {
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

            $detalle->codTributo = NULL;
            $tributos = NULL;
            if ($this->devolucion->iva > 0) {
                $tributos = ['20'];
                $detalle->gravada = $detalle->total;
            } else {
                $detalle->gravada = 0;
                $detalle->exenta = $detalle->total;
            }

            $detalles->push([
                "numItem" => $index + 1,
                "tipoItem" => $detalle->tipo_item,
                "numeroDocumento" => $this->devolucion->venta->dte['identificacion']['codigoGeneracion'],
                "cantidad" => floatval(number_format($detalle->cantidad, 2)),
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
            ]);
        }

        return $detalles;
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
        $this->devolucion = $documento;
        $this->empresa = $this->devolucion->empresa()->first();
        $this->sucursal = $this->devolucion->usuario()->first()->sucursal()->first();
        
        $codigoGeneracion = strtoupper(Uuid::uuid4()->toString());
        $this->caja_codigo = $this->sucursal->codigo_punto_venta ?? 'P001';

        $identificacion = [
            "version" => 2,
            "ambiente" => $dte['identificacion']['ambiente'],
            "codigoGeneracion" => $codigoGeneracion,
            "fecAnula" => $this->devolucion->fecha_anulacion 
                ? Carbon::parse($this->devolucion->fecha_anulacion)->format('Y-m-d')
                : Carbon::now()->format('Y-m-d'),
            "horAnula" => Carbon::now()->format('H:i:s'),
        ];

        // Para Nota de Débito, el receptor tiene estructura similar a CCF
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
            "codigoGeneracionR" => ($this->devolucion->tipo_anulacion == 1 || $this->devolucion->tipo_anulacion == 3) && $this->devolucion->codigo_generacion_remplazo 
                ? $this->devolucion->codigo_generacion_remplazo 
                : NULL,
            "tipoDocumento" => $tipo_documento,
            "numDocumento" => $num_documento,
            "nombre" => $nombre,
            "correo" => $correo,
            "telefono" => $telefono,
        ];

        $tipoAnulacion = $this->devolucion->tipo_anulacion ?? 2;
        $motivoTexto = $this->devolucion->motivo_anulacion ?? 'Se rescinde la operación.';

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
