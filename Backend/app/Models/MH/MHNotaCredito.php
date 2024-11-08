<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Http;
use App\Models\MH\Unidad;
use Luecano\NumeroALetras\NumeroALetras;

class MHNotaCredito extends Model
{

    public $devolucion;
    public $caja;
    public $caja_codigo;
    public $empresa;
    public $sucursal;
    

    public function generarDTE($devolucion){
        $this->devolucion = $devolucion;
        $this->empresa = $this->devolucion->empresa()->first();
        $this->sucursal = $this->devolucion->usuario()->first()->sucursal()->first();

        $this->caja_codigo = '0001';
        $this->devolucion->tipo_dte = '05';
        $this->devolucion->numero_control = 'DTE-'. $this->devolucion->tipo_dte . '-' . $this->sucursal->cod_estable_mh . $this->caja_codigo . '-' .str_pad($this->devolucion->correlativo, 15, '0', STR_PAD_LEFT);

        if (!$this->devolucion->codigo_generacion) {
            $this->devolucion->codigo_generacion = strtoupper(Uuid::uuid4()->toString());
            $this->devolucion->save();
        }

        $this->devolucion->ambiente = $this->empresa->fe_ambiente; // 00 Modo prueba 01 Modo producción
        $this->devolucion->tipoModelo = 1; // 1 Modelo Facturación previo 2 Modelo Facturación diferido
        $this->devolucion->tipoOperacion = 1; // 1 Transmisión normal 2 Transmisión por contingencia
        $this->devolucion->tipoContingencia = NULL; // 1 No disponibilidad de sistema del MH 2 No disponibilidad de sistema del emisor 3 Falla en el suministro de servicio de Internet del Emisor 4 Falla en el suministro de servicio de energía eléctrica del emisor que impida la transmisión de los DTE 5 Otro (deberá digitar un máximo de 500 caracteres explicando el motivo)
        $this->devolucion->motivoContin = NULL;
        $this->devolucion->moneda = 'USD';
        $this->devolucion->version = 3;


        // Condición
            if ($this->devolucion->condicion == 'Crédito'){
                $this->devolucion->cod_condicion = 2; //Credito
            }else{
                $this->devolucion->cod_condicion = 1; //Contado
            }

        // Metodo de pago
            switch ($this->devolucion->forma_pago) {
                case 'Efectivo': //Billetes y monedas
                    $this->devolucion->cod_metodo_pago = '01';
                    break;
                case 'Tarjeta de crédito/débito': //Tarjeta Débito y Credito
                    $this->devolucion->cod_metodo_pago = '02';
                    break;
                case 'Cheque': //Tarjeta Débito
                    $this->devolucion->cod_metodo_pago = '04';
                    break;
                case 'Transferencia': //Transferencia_ Depósito Bancario
                    $this->devolucion->cod_metodo_pago = '05';
                    break;
                case 'Vales': //Vales o Cupones
                    $this->devolucion->cod_metodo_pago = '06';
                    break;
                case 'Chivo Wallet': //Dinero electrónico
                    $this->devolucion->cod_metodo_pago = '09';
                    break;
                case 'Bitcoin': //Dinero electrónico
                    $this->devolucion->cod_metodo_pago = '11';
                    break;
                default:
                    $this->devolucion->cod_metodo_pago = '01';
                    break;
            }

        // Total en letras
        $partes = explode('.', strval( number_format($this->devolucion->total, 2) ));

        $formatter = new NumeroALetras();
        $n = explode(".", number_format($devolucion->total,2));
        
        $dolares = $formatter->toWords(floatval(str_replace(',', '',$n[0])));
        $centavos = $formatter->toWords($n[1]);

        $this->devolucion->total_en_letras = $dolares . ' DÓLARES CON ' . $centavos . ' CENTAVOS.';
        
        return $this->generarNotaCredito();

    } 

    protected function identificador(){
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
            "fecEmi" => \Carbon\Carbon::parse($this->devolucion->fecha)->format('Y-m-d'),
            "horEmi" => \Carbon\Carbon::parse($this->devolucion->created_at)->format('H:i:s'),
            "tipoMoneda" => $this->devolucion->moneda,
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

    protected function receptor(){

        return [
              "nit" =>  $this->devolucion->cliente->nit ? str_replace('-', '', $this->devolucion->cliente->nit) : NULL,
              "nombreComercial" =>  $this->devolucion->cliente->nombre_empresa,
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

    protected function documentoRelacionado(){

        return [
              "tipoDocumento" =>  $this->devolucion->venta->dte['identificacion']['tipoDte'],
              "tipoGeneracion" =>  2,
              "numeroDocumento" =>  $this->devolucion->venta->dte['identificacion']['codigoGeneracion'],
              "fechaEmision" =>  $this->devolucion->venta->dte['identificacion']['fecEmi'],
            ];
    }

    public function generarNotaCredito(){

        $tributos = NULL;

        if ($this->devolucion->iva) {
            $tributos = collect();
            if ($this->devolucion->iva){ 
                $tributos->push(['codigo' => '20', 'descripcion'=> 'Impuesto al Valor Agregado 13%', 'valor' => floatval(number_format($this->devolucion->iva,2))]);
            }
        }

        $this->devolucion->gravada = $this->devolucion->sub_total;

        return 
            [
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
                  "montoTotalOperacion" => floatval(number_format($this->devolucion->total + $this->devolucion->iva_retenido, 2, '.', '')),
                  "totalLetras" => $this->devolucion->total_en_letras,
                  // "totalIva" => floatval(number_format($this->devolucion->iva, 2, '.', '')),
                  "condicionOperacion" => $this->devolucion->cod_condicion,
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

    protected function detalles(){
        $detalles = collect();

        foreach ($this->devolucion->detalles as $index => $detalle) {

            $cod = Unidad::where('nombre', ucfirst($detalle->unidad))->pluck('cod')->first();
            if ($cod){
                $detalle->cod_medida = $cod;
            }else{
                $detalle->cod_medida = 59;
            }

            // Tipo Item
            if ($detalle->producto()->pluck('tipo')->first() == 'Servicio'){
                $detalle->tipo_item = 2;
            }else{
                $detalle->tipo_item = 1;
            }


            $detalle->codTributo = NULL;
            $detalle->gravada = $detalle->total;

            $detalles->push([
                "numItem" => $index + 1,
                "tipoItem" => $detalle->tipo_item,
                "numeroDocumento" => $this->devolucion->venta->dte['identificacion']['codigoGeneracion'],
                "cantidad" => floatval(number_format($detalle->cantidad,2)),
                "codigo" => $detalle->codigo,
                "codTributo" => $detalle->codTributo,
                "uniMedida" => $detalle->cod_medida,
                "descripcion" => $detalle->nombre_producto,
                "precioUni" => floatval(number_format($detalle->precio,4, '.', '')),
                "montoDescu" => floatval(number_format($detalle->descuento,2, '.', '')),
                "ventaNoSuj" => floatval(number_format($detalle->no_sujeta,2, '.', '')),
                "ventaExenta" => floatval(number_format($detalle->exenta,2, '.', '')),
                "ventaGravada" => floatval(number_format($detalle->gravada,2, '.', '')),
                "tributos" => ['20'],
                // "ivaItem" => floatval($detalle->iva)
              ]);
        }

        return $detalles;
    }


}

