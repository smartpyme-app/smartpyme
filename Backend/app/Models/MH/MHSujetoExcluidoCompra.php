<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Luecano\NumeroALetras\NumeroALetras;

class MHSujetoExcluido extends Model
{

    public $compra;
    public $empresa;

    public function generarDTE($compra){
        $this->compra = $compra;
        $this->empresa = $this->compra->empresa()->first();
        // $this->empresa->cod_estable_mh = '0001';
        $this->compra->tipo_dte = '14';

        if (!$this->compra->codigo_generacion) {
            $this->compra->numero_control = 'DTE-'. $this->compra->tipo_dte . '-' . $this->empresa->cod_estable_mh . '0001-' .str_pad($this->compra->referencia, 15, '0', STR_PAD_LEFT);
            $this->compra->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
            $this->compra->save();
        }

        $this->compra->ambiente = $this->empresa->fe_ambiente; // 00 Modo prueba 01 Modo producción
        $this->compra->tipoModelo = 1; // 1 Modelo Facturación previo 2 Modelo Facturación diferido
        $this->compra->tipoOperacion = 1; // 1 Transmisión normal 2 Transmisión por contingencia
        $this->compra->tipoContingencia = NULL; // 1 No disponibilidad de sistema del MH 2 No disponibilidad de sistema del emisor 3 Falla en el suministro de servicio de Internet del Emisor 4 Falla en el suministro de servicio de energía eléctrica del emisor que impida la transmisión de los DTE 5 Otro (deberá digitar un máximo de 500 caracteres explicando el motivo)
        $this->compra->motivoContin = NULL;
        $this->compra->moneda = 'USD';
        $this->compra->version = 1;

        // Condición
            if ($this->compra->condicion == 'Crédito'){
                $this->compra->cod_condicion = 2; //Credito
            }else{
                $this->compra->cod_condicion = 1; //Contado
            }

        // Metodo de pago
            switch ($this->compra->metodo_pago) {
                case 'Efectivo': //Billetes y monedas
                    $this->compra->cod_metodo_pago = '01';
                    break;
                case 'Tarjeta': //Tarjeta Débito y Credito
                    $this->compra->cod_metodo_pago = '02';
                    break;
                case 'Cheque': //Tarjeta Débito
                    $this->compra->cod_metodo_pago = '04';
                    break;
                case 'Transferencia': //Transferencia_ Depósito Bancario
                    $this->compra->cod_metodo_pago = '05';
                    break;
                case 'Vales': //Vales o Cupones
                    $this->compra->cod_metodo_pago = '06';
                    break;
                case 'Chivo Wallet': //Dinero electrónico
                    $this->compra->cod_metodo_pago = '09';
                    break;
                case 'Bitcoin': //Dinero electrónico
                    $this->compra->cod_metodo_pago = '11';
                    break;
                default:
                    $this->compra->cod_metodo_pago = '01';
                    break;
            }

        // Total en letras
        $partes = explode('.', strval( number_format($this->compra->total, 2) ));

        $formatter = new NumeroALetras();
        $n = explode(".", number_format($compra->total,2));
        
        $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
        $centavos = $formatter->toWords($n[1]);

        $this->compra->total_en_letras = $dolares . ' DÓLARES CON ' . $centavos . ' CENTAVOS.';

        return $this->generar();

    }  
    

    public function identificacion(){
        return [
            "version" => $this->compra->version,
            "ambiente" => $this->compra->ambiente,
            "tipoDte" => $this->compra->tipo_dte,
            "numeroControl" => $this->compra->numero_control,
            "codigoGeneracion" => $this->compra->codigo_generacion,
            "tipoModelo" => $this->compra->tipoModelo,
            "tipoOperacion" => $this->compra->tipoOperacion,
            "tipoContingencia" => $this->compra->tipoContingencia,
            "motivoContin" => $this->compra->motivoContin,
            "fecEmi" => \Carbon\Carbon::parse($this->compra->fecha)->format('Y-m-d'),
            "horEmi" => \Carbon\Carbon::parse($this->compra->created_at)->format('H:i:s'),
            "tipoMoneda" => $this->compra->moneda,
        ];
    }

    public function emisor(){
        return [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nrc" => str_replace('-', '', $this->empresa->ncr),
            "nombre" => $this->empresa->nombre,
            "codActividad" => $this->empresa->cod_actividad_economica,
            "descActividad" => $this->empresa->giro,
            "direccion" => [
                "departamento" => $this->empresa->cod_departamento,
                "municipio" => $this->empresa->cod_municipio,
                "complemento" => $this->empresa->direccion,
            ],
            "telefono" => $this->empresa->telefono,
            "codEstableMH" => $this->empresa->cod_estable_mh ? $this->empresa->cod_estable_mh : NULL,
            "codEstable" => $this->empresa->cod_estable ? $this->empresa->cod_estable : NULL,
            "codPuntoVentaMH" => NULL,
            "codPuntoVenta" => NULL,
            "correo" => $this->empresa->correo,
        ];
    }

    public function generar(){

        $tributos = NULL;
        $apendice = NULL;

        if ($this->compra->proveedor->nit) {
            $this->compra->proveedor->tipo_documento = '36';
            $this->compra->proveedor->num_documento = $this->compra->proveedor->nit ? str_replace('-', '', $this->compra->proveedor->nit) : NULL;
        }
        if ($this->compra->proveedor->dui) {
            $this->compra->proveedor->tipo_documento = '13';
            $this->compra->proveedor->num_documento = $this->compra->proveedor->dui ? str_replace('-', '', $this->compra->proveedor->dui) : NULL;
        }

        return 
            [
                "identificacion" => $this->identificacion(),
                "emisor" => $this->emisor(),
                "sujetoExcluido" =>  [
                    "tipoDocumento" => $this->compra->proveedor->tipo_documento, //36 NIT 13 DUI
                    "numDocumento" => $this->compra->proveedor->num_documento,
                    "nombre" => $this->compra->nombre_proveedor,
                    "codActividad" => $this->compra->proveedor->cod_giro ? $this->compra->proveedor->cod_giro : NULL,
                    "descActividad" => $this->compra->proveedor->giro ? $this->compra->proveedor->giro : NULL,
                    "direccion" => [
                        "departamento" => $this->compra->proveedor->cod_departamento,
                        "municipio" => $this->compra->proveedor->cod_municipio,
                        "complemento" => $this->compra->proveedor->direccion
                    ],
                    "telefono" => $this->compra->proveedor->telefono,
                    "correo" => $this->compra->proveedor->correo
                ],
                "cuerpoDocumento" => $this->detalles(),
                "resumen" => [
                  "totalCompra" => floatval(number_format($this->compra->sub_total + $this->compra->iva, 2, '.', '')),
                  "descu" => floatval(number_format($this->compra->descuento, 2, '.', '')),
                  "totalDescu" => floatval(number_format($this->compra->descuento, 2, '.', '')),
                  "subTotal" => floatval(number_format($this->compra->sub_total + $this->compra->iva, 2, '.', '')),
                  "ivaRete1" => floatval(number_format($this->compra->iva_retenido, 2, '.', '')),
                  "reteRenta" => floatval(number_format($this->compra->renta_retenida, 2, '.', '')),
                  "totalPagar" => floatval(number_format($this->compra->total, 2, '.', '')),
                  "totalLetras" => $this->compra->total_en_letras,
                  "condicionOperacion" => $this->compra->cod_condicion,
                  "pagos" => [
                        [
                          "codigo" => $this->compra->cod_metodo_pago,
                          "montoPago" => floatval(number_format($this->compra->total, 2, '.', '')),
                          "referencia" => NULL,
                          "plazo" => NULL,
                          "periodo" => NULL
                        ]
                    ],
                  "observaciones" => null
                ],
                "apendice" => $apendice
            ];
    }

    protected function detalles(){
        $detalles = collect();

        foreach ($this->compra->detalles as $index => $detalle) {

            //Unidad
            $detalle->cod_medida = 59;
            // Tipo Item
            $detalle->tipo_item = 2; //Servicio
            // $detalle->tipo_item = 1; //Producto

            $detalles->push([
                "numItem" => $index + 1,
                "tipoItem" => $detalle->tipo_item,
                "cantidad" => floatval(number_format($detalle->cantidad,4, '.', '')),
                "codigo" => $detalle->codigo,
                "uniMedida" => $detalle->cod_medida,
                "descripcion" => $detalle->nombre_producto,
                "precioUni" => floatval(number_format($detalle->costo + ($detalle->costo * 0.13) ,4, '.', '')),
                "montoDescu" => floatval(number_format($detalle->descuento,2, '.', '')),
                "compra" => floatval(number_format($detalle->total + ($detalle->total * 0.13),2, '.', '')),
              ]);
        }

        return $detalles;
    }


}

