<?php

namespace App\Models\MH;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Http;
use App\Models\MH\Unidad;
use Luecano\NumeroALetras\NumeroALetras;

class MH extends Model
{
    // protected $url_firmado = 'http://localhost:8113/firmardocumento/';
    protected $url_firmado = 'https://firmador.smartpyme.site:8443/firmardocumento/';
    protected $url_mh = 'https://apitest.dtes.mh.gob.sv/fesv/recepciondte';
    protected $url_anular_dte = 'https://apitest.dtes.mh.gob.sv/fesv/anulardte';
    protected $url_auth = 'https://apitest.dtes.mh.gob.sv/seguridad/auth';

    public $venta;
    public $caja;
    public $caja_codigo;
    public $empresa;
    
    public function auth($empresa){
        $this->empresa = $empresa;

        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded', 'User-Agent' => 'Laravel',
        ])->asForm()->post($this->url_auth, [ 'user' => str_replace('-', '', $this->empresa->mh_usuario), 'pwd' => $this->empresa->mh_contrasena ]);

        return $response->json();
    }

    public function firmarDTE($DTE)
    {

        $response = Http::post($this->url_firmado,[
            'nit' => str_replace('-', '', $this->empresa->nit),
            'activo' => true,
            'passwordPri' => $this->empresa->mh_pwd_certificado,
            'dteJson' => $DTE,
        ]);

        return $response->json();

    }

    public function enviarDTE($auth, $DTEFirmado){

        $response = Http::withHeaders(['Content-Type' => 'application/JSON', 'User-Agent' => 'Laravel', 'Authorization' => $auth['body']['token'],
        ])->post($this->url_mh, [
            'ambiente' => $this->venta->ambiente,
            'idEnvio' => $this->venta->correlativo,
            'version' => $this->venta->version,
            'tipoDte' => $this->venta->tipoDte,
            'documento' => $DTEFirmado['body'],
            'codigoGeneracion' => $this->venta->codigoGeneracion
        ]);

        return $response->json();

    }   

    public function generarDTE($venta){
        $this->venta = $venta;
        // $this->caja = $this->venta->caja()->first();
        $this->empresa = $this->venta->empresa()->first();

        $this->caja_codigo = '0001';
        $this->empresa->cod_estable_mh = '0001';
        $this->empresa->tipoEstablecimiento = 'Casa matriz';
        $this->empresa->tipo_establecimiento = '02';

        $this->venta->ambiente = $this->empresa->fe_ambiente; // 00 Modo prueba 01 Modo producción
        $this->venta->tipoModelo = 1; // 1 Modelo Facturación previo 2 Modelo Facturación diferido
        $this->venta->tipoOperacion = 1; // 1 Transmisión normal 2 Transmisión por contingencia
        $this->venta->tipoContingencia = NULL; // 1 No disponibilidad de sistema del MH 2 No disponibilidad de sistema del emisor 3 Falla en el suministro de servicio de Internet del Emisor 4 Falla en el suministro de servicio de energía eléctrica del emisor que impida la transmisión de los DTE 5 Otro (deberá digitar un máximo de 500 caracteres explicando el motivo)
        $this->venta->motivoContin = NULL;
        $this->venta->moneda = 'USD';

        if ($this->venta->nombre_documento == 'Crédito fiscal') {
            $this->venta->tipoDte = '03';
            $this->venta->version = 3;
        }else{
            $this->venta->tipoDte = '01';
            $this->venta->version = 1;
        }

        $this->venta->numeroControl = 'DTE-'. $this->venta->tipoDte . '-' . $this->empresa->cod_estable_mh . $this->caja_codigo . '-' .str_pad($this->venta->correlativo, 15, '0', STR_PAD_LEFT);
        $this->venta->codigoGeneracion = strtoupper(Uuid::uuid4()->toString());

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


        // 01 Factura
        // 03 Comprobante de crédito fiscal
        // 04 Nota de remisión 05 Nota de crédito 06 Nota de débito
        // 07 Comprobante de retención 08 Comprobante de liquidación 09 Documento contable de liquidación 11 Facturas de exportación 14 Factura de sujeto excluido 15 Comprobante de donación

        if ($this->venta->tipoDte == '01') {
            return $this->generarFactura();
        }
        if ($this->venta->tipoDte == '03') {
            return $this->generarCCF();
        }

    } 

    public function generarDTEAnulado($DTE){
        $codigoGeneracion = strtoupper(Uuid::uuid4()->toString());
        // $this->caja = $this->venta->caja()->first();
        $this->caja_codigo = '0001';

        $identificacion = [
            "version" => 2,
            "ambiente" => $DTE['identificacion']['ambiente'],
            "codigoGeneracion" => $codigoGeneracion,
            "fecAnula" => \Carbon\Carbon::now()->format('Y-m-d'),
            "horAnula" => \Carbon\Carbon::now()->format('H:i:s'),
        ];

        $tipo_documento = NULL;
        $num_documento = NULL;


        if ($DTE['identificacion']['tipoDte'] == '01') {
            $tipo_documento = $DTE['receptor']['tipoDocumento'];
            $num_documento = $DTE['receptor']['numDocumento'];
        }

        if ($DTE['identificacion']['tipoDte'] == '03') {
            $tipo_documento = '36';
            $num_documento = $DTE['receptor']['nit'];
        }

        $documento = [
            "tipoDte" => $DTE['identificacion']['tipoDte'],
            "codigoGeneracion" => $DTE['identificacion']['codigoGeneracion'],
            "selloRecibido" => $DTE['sello'],
            "numeroControl" => $DTE['identificacion']['numeroControl'],
            "fecEmi" => $DTE['identificacion']['fecEmi'],
            "montoIva" => isset($DTE['resumen']['totalIva']) ? $DTE['resumen']['totalIva'] : NULL,
            "codigoGeneracionR" => NULL, // Solo si el motivo es error, hay que mandar el que sustituye
            "tipoDocumento" => $tipo_documento,
            "numDocumento" => $num_documento,
            "nombre" => $DTE['receptor']['nombre'],
            "correo" => $DTE['receptor']['correo'],
            "telefono" => $DTE['receptor']['telefono'],
        ];

        // 1. Error en la Información del Documento Tributario Electrónico a invalidar.
        // 2. Rescindir de la operación realizada.
        // 3. Otro.

        $motivo = [
            "tipoAnulacion" => 2,
            "motivoAnulacion" => 'Se rescinde la operación.',
            "nombreResponsable" => $DTE['emisor']['nombre'],
            "tipDocResponsable" => '36',
            "numDocResponsable" => $DTE['emisor']['nit'],
            "nombreSolicita" => $DTE['emisor']['nombre'],
            "tipDocSolicita" =>  '36',
            "numDocSolicita" => $DTE['emisor']['nit'],
        ];

        switch ($this->empresa->tipo_establecimiento) {
            case 'Sucursal':
                $this->empresa->tipoEstablecimiento = '01';
                break;
            case 'Casa matriz':
                $this->empresa->tipoEstablecimiento = '02';
                break;
            case 'Bodega':
                $this->empresa->tipoEstablecimiento = '04';
                break;
            default:
                $this->empresa->tipoEstablecimiento = '02';
                break;
        }


        $emisor = [
            "nit" => str_replace('-', '', $this->empresa->nit),
            "nombre" => $this->empresa->nombre,
            "tipoEstablecimiento" => $this->empresa->tipoEstablecimiento,
            "nomEstablecimiento" => $this->empresa->nombre_comercial,
            "codEstable" => $this->empresa->cod_estable ? $this->empresa->cod_estable : NULL,
            "codPuntoVenta" => $this->caja_codigo ? $this->caja_codigo : NULL,
            "telefono" => $this->empresa->telefono,
            "correo" => $this->empresa->correo,
        ];

        return  
            [
                "identificacion" => $identificacion,
                "emisor" => $emisor,
                "documento" => $documento,
                "motivo" => $motivo,
            ];

    }

    public function anularDTE($auth, $DTE, $DTEFirmado){

        $response = Http::withHeaders(['Content-Type' => 'application/JSON', 'User-Agent' => 'Laravel', 'Authorization' => $auth['body']['token'],
        ])->post($this->url_anular_dte, [
            'ambiente' => $DTE['identificacion']['ambiente'],
            'idEnvio' => $this->venta->id,
            'version' => 2,
            'documento' => $DTEFirmado['body'],
        ]);

        return $response->json();
    }

    protected function identificador(){
        return [
            "version" => $this->venta->version,
            "ambiente" => $this->venta->ambiente,
            "tipoDte" => $this->venta->tipoDte,
            "numeroControl" => $this->venta->numeroControl,
            "codigoGeneracion" => $this->venta->codigoGeneracion,
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
            return NULL;
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
              "nrc" => NULL,
              "nombre" => $this->venta->nombre_cliente,
              "codActividad" => $this->venta->cliente->cod_giro ? $this->venta->cliente->cod_giro : NULL,
              "descActividad" => $this->venta->cliente->giro ? $this->venta->cliente->giro : NULL,
              "direccion" => [
                "departamento" => $this->venta->cliente->cod_departamento,
                "municipio" => $this->venta->cliente->cod_municipio,
                "complemento" => $this->venta->cliente->direccion ? $this->venta->cliente->direccion : $this->venta->cliente->empresa_direccion,
              ],
              "telefono" => $this->venta->cliente->telefono,
              "correo" => $this->venta->cliente->correo
            ];
    }

    protected function receptorCCF(){

        return [
              "nit" =>  $this->venta->cliente->nit ? str_replace('-', '', $this->venta->cliente->nit) : NULL,
              "nombreComercial" =>  $this->venta->cliente->nombre_empresa,
              "nrc" => str_replace('-', '', $this->venta->cliente->ncr),
              "nombre" => $this->venta->cliente->nombre_completo,
              "codActividad" => $this->venta->cliente->cod_giro,
              "descActividad" => $this->venta->cliente->giro,
              "direccion" => [
                "departamento" => $this->venta->cliente->cod_departamento,
                "municipio" => $this->venta->cliente->cod_departamento,
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
                "cuerpoDocumento" => $this->detallesFactura(),
                "resumen" => [
                  "totalNoSuj" => floatval(number_format($this->venta->no_sujeta, 2, '.', '')),
                  "totalExenta" => floatval(number_format($this->venta->exenta, 2, '.', '')),
                  "totalGravada" => floatval(number_format($this->venta->gravada + $this->venta->iva, 2, '.', '')),
                  "subTotalVentas" => floatval(number_format($this->venta->sub_total + $this->venta->iva, 2, '.', '')),
                  "descuNoSuj" => 0,
                  "descuExenta" => 0,
                  "descuGravada" => floatval(number_format($this->venta->descuento, 2, '.', '')),
                  "porcentajeDescuento" => 0,
                  "totalDescu" => floatval(number_format($this->venta->descuento, 2, '.', '')),
                  "tributos" => $tributos,
                  "subTotal" => floatval(number_format($this->venta->sub_total + $this->venta->iva, 2, '.', '')),
                  "ivaRete1" => floatval(number_format($this->venta->iva_retenido, 2, '.', '')),
                  "reteRenta" => 0,
                  "montoTotalOperacion" => floatval(number_format($this->venta->total, 2, '.', '')),
                  "totalNoGravado" => 0,
                  "totalPagar" => floatval(number_format($this->venta->total, 2, '.', '')),
                  "totalLetras" => $this->venta->total_en_letras,
                  "totalIva" => floatval(number_format($this->venta->iva, 2, '.', '')),
                  "saldoFavor" => 0,
                  "condicionOperacion" => $this->venta->cod_condicion,
                  "pagos" => [
                    [
                      "codigo" => $this->venta->cod_metodo_pago,
                      "montoPago" => floatval(number_format($this->venta->total, 2, '.', '')),
                      "referencia" => NULL,
                      "plazo" => NULL,
                      "periodo" => NULL
                    ]
                  ],
                  "numPagoElectronico" => ""
                ],
                "extension" => NULL,
                "apendice" => [
                    [
                    "campo" => "usuario",
                    "etiqueta" => "nombre",
                    "valor" => $this->venta->nombre_usuario
                    ]
                ]
            ];
    }

    protected function detallesFactura(){
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

            $detalle->precio = $detalle->precio + ($detalle->precio * 0.13);
            $detalle->iva = ($detalle->total * 0.13);
            $detalle->gravada = $detalle->total;
            $detalle->total = $detalle->total + $detalle->iva;

            $detalles->push([
                "numItem" => $index + 1,
                "tipoItem" => $detalle->tipo_item,
                "numeroDocumento" => NULL,
                "cantidad" => floatval($detalle->cantidad),
                "codigo" => $detalle->codigo,
                "codTributo" => $detalle->codTributo,
                "uniMedida" => $detalle->cod_medida,
                "descripcion" => $detalle->nombre_producto,
                "precioUni" => floatval(number_format($detalle->precio,2, '.', '')),
                "montoDescu" => floatval(number_format($detalle->descuento,2, '.', '')),
                "ventaNoSuj" => floatval(number_format($detalle->no_sujeta,2, '.', '')),
                "ventaExenta" => floatval(number_format($detalle->exenta,2, '.', '')),
                "ventaGravada" => floatval(number_format($detalle->gravada + $detalle->iva,2, '.', '')),
                "tributos" => $tributos,
                "psv" => 0,
                "noGravado" => 0,
                "ivaItem" => floatval(number_format($detalle->iva,2))
              ]);
        }

        return $detalles;
    }

    public function generarCCF(){

        $tributos = NULL;

        if ($this->venta->iva > 0 || $this->venta->fovial > 0 || $this->venta->cotrans > 0) {
            $tributos = collect();
            if ($this->venta->iva){ 
                $tributos->push(['codigo' => '20', 'descripcion'=> 'Impuesto al Valor Agregado 13%', 'valor' => floatval(number_format($this->venta->iva,2))]);
            }
        }

        $this->venta->gravada = $this->venta->sub_total;

        return 
            [
                "identificacion" => $this->identificador(),
                "documentoRelacionado" => NULL,
                "emisor" => $this->emisor(),
                "receptor" => $this->receptorCCF(),
                "otrosDocumentos" => NULL,
                "ventaTercero" => NULL,
                "cuerpoDocumento" => $this->detallesCCF(),
               "resumen" => [
                  "totalNoSuj" => floatval(number_format($this->venta->no_sujeta, 2, '.', '')),
                  "totalExenta" => floatval(number_format($this->venta->exenta, 2, '.', '')),
                  "totalGravada" => floatval(number_format($this->venta->gravada, 2, '.', '')),
                  "subTotalVentas" => floatval(number_format($this->venta->sub_total, 2, '.', '')),
                  "descuNoSuj" => 0,
                  "descuExenta" => 0,
                  "descuGravada" => floatval(number_format($this->venta->descuento, 2, '.', '')),
                  "porcentajeDescuento" => 0,
                  "totalDescu" => floatval(number_format($this->venta->descuento, 2, '.', '')),
                  "tributos" => $tributos,
                  "subTotal" => floatval(number_format($this->venta->sub_total, 2, '.', '')),
                  "ivaPerci1" => floatval(number_format($this->venta->iva_percibido, 2, '.', '')),
                  "ivaRete1" => floatval(number_format($this->venta->iva_retenido, 2, '.', '')),
                  "reteRenta" => 0,
                  "montoTotalOperacion" => floatval(number_format($this->venta->total, 2, '.', '')),
                  "totalNoGravado" => 0,
                  "totalPagar" => floatval(number_format($this->venta->total, 2, '.', '')),
                  "totalLetras" => $this->venta->total_en_letras,
                  // "totalIva" => floatval(number_format($this->venta->iva, 2, '.', '')),
                  "saldoFavor" => 0,
                  "condicionOperacion" => $this->venta->cod_condicion,
                  "pagos" => [
                    [
                      "codigo" => $this->venta->cod_metodo_pago,
                      "montoPago" => floatval(number_format($this->venta->total, 2, '.', '')),
                      "referencia" => NULL,
                      "plazo" => NULL,
                      "periodo" => NULL
                    ]
                  ],
                  "numPagoElectronico" => ""
                ],
                "extension" => NULL,
                "apendice" => [
                    [
                    "campo" => "usuario",
                    "etiqueta" => "nombre",
                    "valor" => $this->venta->nombre_usuario
                    ]
                ]
            ];
    }

    protected function detallesCCF(){
        $detalles = collect();

        foreach ($this->venta->detalles->take(1) as $index => $detalle) {

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
                "numeroDocumento" => NULL,
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
                "psv" => 0,
                "noGravado" => 0,
                // "ivaItem" => floatval($detalle->iva)
              ]);
        }

        return $detalles;
    }


}

