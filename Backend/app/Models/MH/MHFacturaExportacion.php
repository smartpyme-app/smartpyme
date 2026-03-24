<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Http;
use App\Models\MH\Unidad;
use Luecano\NumeroALetras\NumeroALetras;
use Carbon\Carbon;

class MHFacturaExportacion extends Model
{

    public $venta;
    public $caja;
    public $caja_codigo;
    public $empresa;
    public $sucursal;
    

    public function generarDTE($venta){
        $this->venta = $venta;
        $this->empresa = $this->venta->empresa()->first();
        $this->sucursal = $this->venta->sucursal()->first();

        $this->caja_codigo = $this->sucursal->codigo_punto_venta ?? 'P001';
        $this->venta->tipo_dte = '11';
        $this->venta->numero_control = 'DTE-'. $this->venta->tipo_dte . '-' . $this->sucursal->cod_estable_mh . $this->caja_codigo . '-' .str_pad($this->venta->correlativo, 15, '0', STR_PAD_LEFT);

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
                case 'Tarjeta': //Tarjeta Débito y Credito
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
        $partes = explode('.', strval( number_format($this->venta->total, 2) ));

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
            "motivoContigencia" => NULL,
            "fecEmi" => \Carbon\Carbon::parse($this->venta->fecha)->format('Y-m-d'),
            "horEmi" => \Carbon\Carbon::parse($this->venta->created_at)->format('H:i:s'),
            "tipoMoneda" => $this->venta->moneda,
        ];
    }

    protected function emisor(){

        // Tipo Item
        // if ($this->venta->detalles()->first()->producto()->pluck('tipo')->first() == 'Servicio'){
        //     $tipo_item = 2;
        // }else{
        //     $tipo_item = 1;
        // }
        
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
            "codEstableMH" => $this->empresa->cod_estable_mh ? $this->empresa->cod_estable_mh : NULL,
            "codEstable" => $this->empresa->cod_estable ? $this->empresa->cod_estable : NULL,
            "codPuntoVentaMH" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "codPuntoVenta" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "correo" => $this->empresa->correo,
            "tipoItemExpor" => $this->venta->detalles()->first()->producto()->pluck('tipo')->first() == 'Servicio' ? 2 : 1,
            // "tipoItemExpor" => (int) $this->venta->tipo_item_export,
            "recintoFiscal" => $this->venta->recinto_fiscal ?? NULL, //Punto de Aduana
            "regimen" => $this->venta->regimen ?? NULL,
        ];
    }

    protected function receptor(){

        if (!$this->venta->id_cliente || !$this->venta->cliente) {
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

        return [
              "tipoDocumento" => $this->venta->cliente->tipo_documento ?? '36', //36 NIT 13 DUI
              "numDocumento" => $this->venta->cliente->dui ?? str_replace('-', '', $this->venta->cliente->nit),
              "nombre" => $this->venta->nombre_cliente,
              "nombreComercial" => $this->venta->cliente->nombre_empresa,
              "descActividad" => $this->venta->cliente->giro ? $this->venta->cliente->giro : NULL,
              "codPais" => $this->venta->cliente->cod_pais,
              "nombrePais" => $this->venta->cliente->pais,
              "complemento" => $this->venta->cliente->direccion ? $this->venta->cliente->direccion : $this->venta->cliente->empresa_direccion,
              "tipoPersona" => ($this->venta->cliente->tipo_persona == 'Persona Natural') ? 1 : 2,
              "telefono" => $this->venta->cliente->telefono,
              "correo" => $this->venta->cliente->correo ?: ($this->venta->ambiente == '00' ? "prueba@ejemplo.com" : NULL)
            ];
    }

    public function generarFactura(){
        $tributos = NULL;

        if ($this->venta->ambiente == '00') {
            $totalGravada = 0;
            foreach ($this->venta->detalles as $detalle) {
                $totalGravada += $detalle->total;
            }
        } else {
            $totalGravada = $this->venta->total;
        }

        return 
            [
                "identificacion" => $this->identificador(),
                "emisor" => $this->emisor(),
                "receptor" => $this->receptor(),
                "otrosDocumentos" => NULL,
                "ventaTercero" => NULL,
                "cuerpoDocumento" => $this->detalles(),
                "resumen" => [
                  "totalGravada" => floatval(number_format($totalGravada, 2, '.', '')),
                  "descuento" => floatval(number_format(0, 2, '.', '')),
                  "porcentajeDescuento" => 0,
                  "totalDescu" => floatval(number_format(0, 2, '.', '')),
                  "seguro" => floatval(number_format($this->venta->seguro, 2, '.', '')),
                  "flete" => floatval(number_format($this->venta->flete, 2, '.', '')),
                  "montoTotalOperacion" => floatval(number_format($totalGravada, 2, '.', '')),
                  "totalNoGravado" => 0,
                  "totalPagar" => floatval(number_format($totalGravada, 2, '.', '')),
                  "totalLetras" => $this->venta->total_en_letras,
                  "condicionOperacion" => $this->venta->cod_condicion,
                  "pagos" => [
                    [
                      "codigo" => $this->venta->cod_metodo_pago,
                      "montoPago" => floatval(number_format($totalGravada, 2, '.', '')),
                      "referencia" => NULL,
                      "plazo" => $this->venta->cod_condicion == 2 ? $this->obtenerPlazo($this->venta->dias_credito) : NULL,
                      "periodo" => $this->venta->cod_condicion == 2 ? Carbon::parse($this->venta->fecha)->diffInDays(Carbon::parse($this->venta->fecha_pago), false) : NULL
                    ]
                  ],
                    "numPagoElectronico" => "",
                    "codIncoterms" => $this->venta->cod_incoterm,
                    "descIncoterms" => $this->venta->incoterm,
                    "observaciones" => NULL,
                ],
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

        foreach ($this->venta->detalles as $index => $detalle) {

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

            $tributos = NULL;

            $detalle->codTributo = NULL;

            // if ($this->venta->iva > 0) {
            //     $detalle->precio = $detalle->precio + ($detalle->precio * 0.13);
            //     $detalle->iva = ($detalle->total * 0.13);
            //     $detalle->total = $detalle->total + $detalle->iva;
            // }else{
                $detalle->gravada = $detalle->total;
            // }

            $detalles->push([
                "numItem" => $index + 1,
                "codigo" => $detalle->codigo,
                "descripcion" => $detalle->nombre_producto,
                "cantidad" => floatval($detalle->cantidad),
                "uniMedida" => $detalle->cod_medida,
                "precioUni" => floatval(number_format($detalle->precio,2, '.', '')),
                "montoDescu" => floatval(number_format($detalle->descuento,2, '.', '')),
                "ventaGravada" => floatval(number_format($detalle->gravada,2, '.', '')),
                "tributos" => $tributos,
                "noGravado" => 0,
              ]);
        }

        return $detalles;
    }

    private function obtenerPlazo($dias_credito) {
        if ($dias_credito <= 30) {
            return "01"; // Corto plazo
        } elseif ($dias_credito <= 60) {
            return "02"; // Mediano plazo
        } else {
            return "03"; // Largo plazo
        }
    }

}

