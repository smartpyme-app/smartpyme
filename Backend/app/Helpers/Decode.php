<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Decode
{
    public static function jwt_client($token)
    {
        $tokenParts = explode(".", $token);
        $tokenHeader = base64_decode($tokenParts[0]);
        $tokenPayload = base64_decode($tokenParts[1]);
        $jwtHeader = json_decode($tokenHeader);
        $jwtPayload = json_decode($tokenPayload);
        return $jwtPayload;
        //return response()->json($jwtPayload->aud);
    }
}