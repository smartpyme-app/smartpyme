<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Luecano\NumeroALetras\NumeroALetras;

class MHSujetoExcluidoGasto extends Model
{

    public $gasto;
    public $empresa;
    public $caja_codigo;
    public $sucursal;

    public function generarDTE($gasto){
        $this->gasto = $gasto;
        $this->empresa = $this->gasto->empresa()->first();
        $this->sucursal = $this->gasto->sucursal()->first();

        $this->gasto->tipo_dte = '14';
        $this->caja_codigo = $this->sucursal->codigo_punto_venta ?? 'P001';
        $this->gasto->numero_control = 'DTE-'. $this->gasto->tipo_dte . '-' . $this->sucursal->cod_estable_mh . $this->caja_codigo . '-' .str_pad($this->gasto->referencia, 15, '0', STR_PAD_LEFT);

        if (!$this->gasto->codigo_generacion) {
            $this->gasto->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
            $this->gasto->save();
        }

        $this->gasto->ambiente = $this->empresa->fe_ambiente; // 00 Modo prueba 01 Modo producción
        $this->gasto->tipoModelo = 1; // 1 Modelo Facturación previo 2 Modelo Facturación diferido
        $this->gasto->tipoOperacion = 1; // 1 Transmisión normal 2 Transmisión por contingencia
        $this->gasto->tipoContingencia = NULL; // 1 No disponibilidad de sistema del MH 2 No disponibilidad de sistema del emisor 3 Falla en el suministro de servicio de Internet del Emisor 4 Falla en el suministro de servicio de energía eléctrica del emisor que impida la transmisión de los DTE 5 Otro (deberá digitar un máximo de 500 caracteres explicando el motivo)
        $this->gasto->motivoContin = NULL;
        $this->gasto->moneda = 'USD';
        $this->gasto->version = 1;

        // Condición
            if ($this->gasto->condicion == 'Crédito'){
                $this->gasto->cod_condicion = 2; //Credito
            }else{
                $this->gasto->cod_condicion = 1; //Contado
            }

        // Metodo de pago
            switch ($this->gasto->forma_pago) {
                case 'Efectivo': //Billetes y monedas
                    $this->gasto->cod_metodo_pago = '01';
                    break;
                case 'Tarjeta de crédito/débito': //Tarjeta Débito y Credito
                    $this->gasto->cod_metodo_pago = '02';
                    break;
                case 'Cheque': //Tarjeta Débito
                    $this->gasto->cod_metodo_pago = '04';
                    break;
                case 'Transferencia': //Transferencia_ Depósito Bancario
                    $this->gasto->cod_metodo_pago = '05';
                    break;
                case 'Vales': //Vales o Cupones
                    $this->gasto->cod_metodo_pago = '06';
                    break;
                case 'Chivo Wallet': //Dinero electrónico
                    $this->gasto->cod_metodo_pago = '09';
                    break;
                case 'Bitcoin': //Dinero electrónico
                    $this->gasto->cod_metodo_pago = '11';
                    break;
                default:
                    $this->gasto->cod_metodo_pago = '01';
                    break;
            }

        // Total en letras
        $partes = explode('.', strval( number_format($this->gasto->total, 2) ));

        $formatter = new NumeroALetras();
        $n = explode(".", number_format($gasto->total,2));
        
        $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
        $centavos = $formatter->toWords($n[1]);

        $this->gasto->total_en_letras = $dolares . ' DÓLARES CON ' . $centavos . ' CENTAVOS.';

        return $this->generar();

    }  
    

    public function identificacion(){
        return [
            "version" => $this->gasto->version,
            "ambiente" => $this->gasto->ambiente,
            "tipoDte" => $this->gasto->tipo_dte,
            "numeroControl" => $this->gasto->numero_control,
            "codigoGeneracion" => $this->gasto->codigo_generacion,
            "tipoModelo" => $this->gasto->tipoModelo,
            "tipoOperacion" => $this->gasto->tipoOperacion,
            "tipoContingencia" => $this->gasto->tipoContingencia,
            "motivoContin" => $this->gasto->motivoContin,
            "fecEmi" => \Carbon\Carbon::parse($this->gasto->fecha)->format('Y-m-d'),
            "horEmi" => \Carbon\Carbon::parse($this->gasto->created_at)->format('H:i:s'),
            "tipoMoneda" => $this->gasto->moneda,
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
            "codEstableMH" => $this->sucursal->cod_estable_mh ? $this->sucursal->cod_estable_mh : NULL,
            "codEstable" => $this->sucursal->cod_estable_mh ? $this->sucursal->cod_estable_mh : NULL,
            "codPuntoVentaMH" => NULL,
            "codPuntoVenta" => NULL,
            "correo" => $this->empresa->correo,
        ];
    }

    public function generar(){

        $tributos = NULL;
        $apendice = NULL;
        $this->gasto->proveedor = $this->gasto->proveedor()->first();

        if ($this->gasto->proveedor->nit) {
            $this->gasto->proveedor->tipo_documento = '36';
            $this->gasto->proveedor->num_documento = $this->gasto->proveedor->nit ? str_replace('-', '', $this->gasto->proveedor->nit) : NULL;
        }
        if ($this->gasto->proveedor->dui) {
            $this->gasto->proveedor->tipo_documento = '13';
            $this->gasto->proveedor->num_documento = $this->gasto->proveedor->dui ? str_replace('-', '', $this->gasto->proveedor->dui) : NULL;
        }

        return 
            [
                "identificacion" => $this->identificacion(),
                "emisor" => $this->emisor(),
                "sujetoExcluido" =>  [
                    "tipoDocumento" => $this->gasto->proveedor->tipo_documento, //36 NIT 13 DUI
                    "numDocumento" => $this->gasto->proveedor->num_documento,
                    "nombre" => $this->gasto->nombre_proveedor,
                    "codActividad" => $this->gasto->proveedor->cod_giro ? $this->gasto->proveedor->cod_giro : NULL,
                    "descActividad" => $this->gasto->proveedor->giro ? $this->gasto->proveedor->giro : NULL,
                    "direccion" => [
                        "departamento" => $this->gasto->proveedor->cod_departamento,
                        "municipio" => $this->gasto->proveedor->cod_municipio,
                        "complemento" => $this->gasto->proveedor->direccion
                    ],
                    "telefono" => $this->gasto->proveedor->telefono,
                    "correo" => $this->gasto->proveedor->correo
                ],
                "cuerpoDocumento" => $this->detalles(),
                "resumen" => [
                  "totalCompra" => floatval(number_format($this->gasto->sub_total, 2, '.', '')),
                  "descu" => floatval(number_format($this->gasto->descuento, 2, '.', '')),
                  "totalDescu" => floatval(number_format($this->gasto->descuento, 2, '.', '')),
                  "subTotal" => floatval(number_format($this->gasto->sub_total + $this->gasto->iva, 2, '.', '')),
                  "ivaRete1" => floatval(number_format($this->gasto->iva_retenido, 2, '.', '')),
                  "reteRenta" => floatval(number_format($this->gasto->renta_retenida, 2, '.', '')),
                  "totalPagar" => floatval(number_format($this->gasto->total, 2, '.', '')),
                  "totalLetras" => $this->gasto->total_en_letras,
                  "condicionOperacion" => $this->gasto->cod_condicion,
                  "pagos" => [
                        [
                          "codigo" => $this->gasto->cod_metodo_pago,
                          "montoPago" => floatval(number_format($this->gasto->total, 2, '.', '')),
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
        $codMedida = 59;
        $tipoItem = 2; // Servicio
        $this->gasto->cod_medida = $codMedida;
        $this->gasto->tipo_item = $tipoItem;

        $itemsDetalle = $this->gasto->detalles()->orderBy('numero_item')->get();

        if ($itemsDetalle->isNotEmpty()) {
            foreach ($itemsDetalle as $index => $detalle) {
                $esGravada = ($detalle->tipo_gravado ?? '') === 'gravada' || $detalle->aplica_iva;
                $precioUni = $esGravada
                    ? floatval($detalle->sub_total) + floatval($detalle->iva)
                    : floatval($detalle->sub_total);
                $compra = floatval($detalle->total);
                $descuento = 0;
                $detalles->push([
                    "numItem" => $index + 1,
                    "tipoItem" => $tipoItem,
                    "cantidad" => floatval(number_format($detalle->cantidad ?? 1, 4, '.', '')),
                    "codigo" => $this->gasto->codigo ?? null,
                    "uniMedida" => $codMedida,
                    "descripcion" => $detalle->concepto,
                    "precioUni" => floatval(number_format($precioUni, 4, '.', '')),
                    "montoDescu" => floatval(number_format($descuento, 2, '.', '')),
                    "compra" => floatval(number_format($compra, 2, '.', '')),
                ]);
            }
        } else {
            $subIva = floatval($this->gasto->sub_total ?? 0) + floatval($this->gasto->iva ?? 0);
            $descuento = floatval($this->gasto->descuento ?? 0);
            $detalles->push([
                "numItem" => 1,
                "tipoItem" => $tipoItem,
                "cantidad" => floatval(number_format(1, 4, '.', '')),
                "codigo" => $this->gasto->codigo ?? null,
                "uniMedida" => $codMedida,
                "descripcion" => $this->gasto->concepto,
                "precioUni" => floatval(number_format($subIva, 4, '.', '')),
                "montoDescu" => floatval(number_format($descuento, 2, '.', '')),
                "compra" => floatval(number_format($subIva, 2, '.', '')),
            ]);
        }

        return $detalles;
    }


}

