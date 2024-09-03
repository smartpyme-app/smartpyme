<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Http;
use App\Models\MH\Unidad;
use Luecano\NumeroALetras\NumeroALetras;

class MHComprobanteRetencion extends Model
{

    public $venta;
    public $caja;
    public $caja_codigo;
    public $empresa;
    

    public function generarDTE($venta){
        $this->venta = $venta;
        $this->empresa = $this->venta->empresa()->first();

        $this->caja_codigo = '0001';
        // $this->empresa->cod_estable_mh = '0001';
        $this->empresa->tipoEstablecimiento = 'Casa matriz';
        $this->empresa->tipo_establecimiento = '02';
        $this->venta->tipo_dte = '07';
        $this->venta->numero_control = 'DTE-'. $this->venta->tipo_dte . '-' . $this->empresa->cod_estable_mh . $this->caja_codigo . '-' .str_pad($this->venta->correlativo, 15, '0', STR_PAD_LEFT);

        if (!$this->venta->codigo_generacion) {
            $this->venta->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
            $this->venta->save();
        }

        $this->venta->ambiente = $this->empresa->fe_ambiente; // 00 Modo prueba 01 Modo producción
        $this->venta->tipoModelo = 1; // 1 Modelo Facturación previo 2 Modelo Facturación diferido
        $this->venta->tipoOperacion = 1; // 1 Transmisión normal 2 Transmisión por contingencia
        $this->venta->tipoContingencia = NULL; // 1 No disponibilidad de sistema del MH 2 No disponibilidad de sistema del emisor 3 Falla en el suministro de servicio de Internet del Emisor 4 Falla en el suministro de servicio de energía eléctrica del emisor que impida la transmisión de los DTE 5 Otro (deberá digitar un máximo de 500 caracteres explicando el motivo)
        $this->venta->motivoContin = NULL;
        $this->venta->moneda = 'USD';
        $this->venta->version = 1;


        // Condición
            if ($this->venta->condicion == 'Crédito'){
                $this->venta->cod_condicion = 2; //Credito
            }else{
                $this->venta->cod_condicion = 1; //Contado
            }

        // Metodo de pago
            switch ($this->venta->metodo_pago) {
                case 'Efectivo': //Billetes y monedas
                    $this->venta->cod_metodo_pago = '01';
                    break;
                case 'Tarjeta de crédito/débito': //Tarjeta Débito y Credito
                    $this->venta->cod_metodo_pago = '02';
                    break;
                case 'Cheque': //Tarjeta Débito
                    $this->venta->cod_metodo_pago = '04';
                    break;
                case 'Transferencia': //Transferencia_ Depósito Bancario
                    $this->venta->cod_metodo_pago = '05';
                    break;
                case 'Vales': //Vales o Cupones
                    $this->venta->cod_metodo_pago = '06';
                    break;
                case 'Chivo Wallet': //Dinero electrónico
                    $this->venta->cod_metodo_pago = '09';
                    break;
                case 'Bitcoin': //Dinero electrónico
                    $this->venta->cod_metodo_pago = '11';
                    break;
                default:
                    $this->venta->cod_metodo_pago = '01';
                    break;
            }

        // Total en letras
        $partes = explode('.', strval( number_format($this->venta->iva_retenido, 2) ));

        $formatter = new NumeroALetras();
        $n = explode(".", number_format($venta->total,2));
        
        $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
        $centavos = $formatter->toWords($n[1]);

        $this->venta->total_en_letras = $dolares . ' DÓLARES CON ' . $centavos . ' CENTAVOS.';

        return $this->generarFactura();

    } 

    protected function identificador(){
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
            "fecEmi" => \Carbon\Carbon::parse($this->venta->fecha)->format('Y-m-d'),
            "horEmi" => \Carbon\Carbon::parse($this->venta->created_at)->format('H:i:s'),
            "tipoMoneda" => $this->venta->moneda,
        ];
    }

    protected function emisor(){
        
        return [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nrc" => str_replace('-', '', $this->empresa->ncr),
            "nombre" => $this->empresa->nombre,
            "codActividad" => $this->empresa->cod_actividad_economica,
            "descActividad" => $this->empresa->giro,
            "nombreComercial" => $this->empresa->nombre_comercial,
            "tipoEstablecimiento" => $this->empresa->tipo_establecimiento,
            "direccion" => [
                "departamento" => $this->empresa->cod_departamento,
                "municipio" => $this->empresa->cod_municipio,
                "complemento" => $this->empresa->direccion,
            ],
            "telefono" => $this->empresa->telefono,
            "codEstableMH" => $this->empresa->cod_estable_mh ? $this->empresa->cod_estable_mh : NULL,
            "codEstable" => $this->empresa->cod_estable ? $this->empresa->cod_estable : NULL,
            "codPuntoVentaMH" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "codPuntoVenta" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "correo" => $this->empresa->correo,
        ];
    }

    protected function receptor(){

        if (!$this->venta->id_cliente) {
            return [
                "tipoDocumento" => NULL, //36 NIT 13 DUI
                "numDocumento" => NULL,
                "nrc" => NULL,
                "nombre" => 'Consumidor Final',
                "codActividad" => NULL,
                "descActividad" => NULL,
                "direccion" => NULL,
                "telefono" => NULL,
                "correo" => NULL
            ];
        }

        if ($this->venta->cliente->nit) {
            $this->venta->cliente->tipo_documento = '36';
            $this->venta->cliente->num_documento = $this->venta->cliente->nit ? str_replace('-', '', $this->venta->cliente->nit) : NULL;
        }
        if ($this->venta->cliente->dui) {
            $this->venta->cliente->tipo_documento = '13';
            $this->venta->cliente->num_documento = $this->venta->cliente->dui ? $this->venta->cliente->dui : NULL;
        }

        return [
              "tipoDocumento" => $this->venta->cliente->tipo_documento, //36 NIT 13 DUI
              "numDocumento" => $this->venta->cliente->num_documento,
              "nrc" => str_replace('-', '', $this->venta->cliente->ncr),
              "nombre" => $this->venta->nombre_cliente,
              "codActividad" => $this->venta->cliente->cod_giro ? $this->venta->cliente->cod_giro : NULL,
              "descActividad" => $this->venta->cliente->giro ? $this->venta->cliente->giro : NULL,
              "nombreComercial" =>  $this->venta->cliente->nombre_empresa,
              "direccion" => [
                "departamento" => $this->venta->cliente->cod_departamento,
                "municipio" => $this->venta->cliente->cod_municipio,
                "complemento" => $this->venta->cliente->direccion ? $this->venta->cliente->direccion : $this->venta->cliente->empresa_direccion,
              ],
              "telefono" => $this->venta->cliente->telefono,
              "correo" => $this->venta->cliente->correo
            ];
    }

    public function generarFactura(){
        $tributos = NULL;

        $this->venta->gravada = $this->venta->sub_total;

        return 
            [
                "identificacion" => $this->identificador(),
                "documentoRelacionado" => NULL,
                "emisor" => $this->emisor(),
                "receptor" => $this->receptor(),
                "otrosDocumentos" => NULL,
                "ventaTercero" => NULL,
                "cuerpoDocumento" => $this->detalles(),
                "resumen" => [
                  "totalSujetoRetencion" => floatval(number_format($this->venta->sub_total, 2, '.', '')),
                  "totalIVAretenido" => floatval(number_format($this->venta->iva_retenido, 2, '.', '')),
                  "totalIVAretenidoLetras" => floatval(number_format($this->venta->total_en_letras, 2, '.', '')),
                ],
                "extension" => NULL,
                "apendice" => [
                    [
                    "campo" => "empleado",
                    "etiqueta" => "nombre",
                    "valor" => $this->venta->nombre_usuario
                    ]
                ]
            ];
    }

    protected function detalles(){
        $detalles = collect();

        // foreach ($this->venta->detalles as $index => $detalle) {

        // Codigo RetencionMH
        // 22 Retención IVA 1%
        // C4 Retención IVA 13%
        // C9 Otras retenciones IVA casos especiales 

            $detalles->push([
                "numItem" => $index + 1,
                "tipoDte" => $DTE['identificacion']['tipoDte'],
                "tipoDoc" => $DTE['identificacion']['tipoModelo'],
                "numDocumento" => $DTE['identificacion']['codigoGeneracion'],
                "fechaEmision" => $DTE['identificacion']['fechaEmision'],
                "montoSujetoGrav" => $venta->total,
                "codigoRetencionMH" => 22,
                "ivaRetenido" => $venta->iva_retenido,
                "descripcion" => '',
              ]);
        // }

        return $detalles;
    }


}

