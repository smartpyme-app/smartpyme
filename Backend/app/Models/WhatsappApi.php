<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class WhatsappApi extends Model
{

    public function send($type, $to, $text){
        try {
            $token = 'EAAQVCDyyWRwBANjqKDpgSZCaOjCwDDJ1ifMUncZCiy7RGZB230l11deE0Ko90x1g7LZBp4pcfjcFRfKiugLGpKnFNyqACaGNeKJX2D1psik8fBVl2EAJYaf6mMAQEbWT5ZAv1PXWINEHOweVVicoLkbZBpA4QGtA9SZBhuq59V144pfpun0ZB0f7jcjCtOWobQsBw7Si2f8FCcYlBxqMMy77SWs63lb5j0oZD';
            $phoneId ='111482525174098';
            $version ='v15.0';
            $url ='https://graph.facebook.com';
            $payload =[
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => $type,
                'text' => ['preview_url' => false, 'body' => $text ]
                // 'template' => ['name' => 'hello_world', 'language' => [ 'code'=>'en_US'] ]
            ];

            $message = Http::withToken($token)->post($url . '/' . $version . '/' . $phoneId . '/messages', $payload)->throw()->json();

            return ['success' => true, 'message' => $message];


        } catch (Exception $e) {

            return ['success' => false, 'message' => $e->getMessage()];
                
        }
    }


}
