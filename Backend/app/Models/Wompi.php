<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Wompi extends Model
{
    private $token;
    private $transaction;
    private $url;
    private $correoNotificacion = 'alvarado.websis@gmail.com';
    private $aplicativo;
    private $id;
    private $secret;

    public function __construct($empresa)
    {
        $this->aplicativo = $empresa->wompi_aplicativo;
        $this->id = $empresa->wompi_id;
        $this->secret = $empresa->wompi_secret;

        $this->url = asset('/pago-wompi');

    }

    public function autenticate(){
        try {
            
            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://id.wompi.sv/connect/token",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => "grant_type=client_credentials&client_id=".$this->id."&client_secret=".$this->secret."&audience=wompi_api",
              CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
              ),
            ));

            $response = curl_exec($curl);
            $error = curl_error($curl);

            curl_close($curl);

            if ($error){
                return json_decode($err,true);
            }else{
                $this->token = json_decode($response,true);
                return json_decode($response,true);
            }

        } catch (Exception $e) {

            return $e->getMessage();
                
        }
    }

    public function transaction($request){

        $data = [
            "tarjetaCreditoDebido" => [
                "numeroTarjeta" => $request->numeroTarjeta,
                "cvv" => $request->cvv,
                "mesVencimiento" => $request->mesVencimiento,
                "anioVencimiento" => $request->anioVencimiento
            ],
            "monto" => $request->monto,
            "emailCliente" => $request->correo,
            "nombreCliente" => $request->nombre,
            "formaPago" => "PagoNormal",
            // "configuracion" => [
            //     "emailsNotificacion" => $this->correoNotificacion,
            //     "urlWebhook" => $this->url,
            //     "notificarTransaccionCliente" => $request->correo ? true : false
            // ]
        ];

        $payload = json_encode($data);

        $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.wompi.sv/TransaccionCompra",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 60,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => $payload,
              CURLOPT_HTTPHEADER => array(
                "authorization: " . $this->token['token_type'] . ' ' . $this->token['access_token'],
                "content-type: application/json"
              ),
        ));

        $this->transaccion = json_decode(curl_exec($curl), true);
        $err = json_decode(curl_error($curl), true);

        curl_close($curl);

        if ($err)
            return ['success' => false, 'message' => $err];

        return ['success' => true, 'message' => $this->transaccion];
    }

    public function link($request){

        $data = [
            "idAplicativo" => $this->id,
            "identificadorEnlaceComercio" => $this->aplicativo,
            "monto" => $request->monto,
            "nombreProducto" => $request->nombre,
            "formaPago" => [
                "permitirTarjetaCreditoDebido" => true,
                "permitirPagoConPuntoAgricola" => true,
                "permitirPagoEnCuotasAgricola" => true,
                "permitirPagoEnBitcoin" => true
            ],
            "cantidadMaximaCuotas" => "Tres",
            "infoProducto" => [
                "descripcionProducto" => $request->descripcion,
                "urlImagenProducto" => $request->img
            ],
            "configuracion" => [
                "urlRedirect" => $this->url,
                "esMontoEditable" => false,
                "esCantidadEditable" => false,
                "cantidadPorDefecto" => 1,
                "duracionInterfazIntentoMinutos" => 60,
                "urlRetorno" => $this->url,
                "emailsNotificacion" => $this->correoNotificacion,
                // "urlWebhook" => "string",
                // "telefonosNotificacion" => "string",
                "notificarTransaccionCliente" => true
            ],
            "vigencia" => [
                "fechaInicio" => \Carbon\Carbon::today(),
                "fechaFin" => \Carbon\Carbon::today()->addDays(10)
            ],
            "limitesDeUso" => [
                "cantidadMaximaPagosExitosos" => 1,
                "cantidadMaximaPagosFallidos" => 10
            ]
        ];

        $payload = json_encode($data);
        // return $payload;
        $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.wompi.sv/EnlacePago",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 60,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => $payload,
              CURLOPT_HTTPHEADER => array(
                "authorization: " . $this->token['token_type'] . ' ' . $this->token['access_token'],
                "content-type: application/json"
              ),
        ));

        $this->transaccion = json_decode(curl_exec($curl), true);
        $err = json_decode(curl_error($curl), true);

        curl_close($curl);

        if ($err)
            return $err;

        return $this->transaccion;
    }

    public function getLink($id){

        // $payload = json_encode($data);
        // return $payload;
        $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.wompi.sv/EnlacePago/" . $id,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 60,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "GET",
              // CURLOPT_POSTFIELDS => $payload,
              CURLOPT_HTTPHEADER => array(
                "authorization: " . $this->token['token_type'] . ' ' . $this->token['access_token'],
                "content-type: application/json"
              ),
        ));

        $this->transaccion = json_decode(curl_exec($curl), true);
        $err = json_decode(curl_error($curl), true);

        curl_close($curl);

        if ($err)
            return $err;

        return $this->transaccion;
    }


}
