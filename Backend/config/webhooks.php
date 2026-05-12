<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Timeout (segundos) para POST saliente al facturar paquete
    |--------------------------------------------------------------------------
    */
    'paquete_venta_timeout' => (int) env('WEBHOOK_PAQUETE_VENTA_TIMEOUT', 15),

];
