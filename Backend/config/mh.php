<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cliente HTTP hacia api.dtes.mh.gob.sv (Ministerio de Hacienda SV)
    |--------------------------------------------------------------------------
    */

    'verify_ssl' => filter_var(env('MH_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),

    'timeout_seconds' => (int) env('MH_HTTP_TIMEOUT', 120),

    'connect_timeout_seconds' => (int) env('MH_HTTP_CONNECT_TIMEOUT', 30),

];
