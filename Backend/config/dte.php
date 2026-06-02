<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de Laravel Filesystem para DTE en S3
    |--------------------------------------------------------------------------
    */
    'disk' => env('DTE_S3_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | TTL de URLs firmadas (minutos) cuando se expongan al cliente
    |--------------------------------------------------------------------------
    */
    'presigned_minutes' => (int) env('DTE_S3_PRESIGNED_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Habilitar migración programada (schedule en Console\Kernel)
    |--------------------------------------------------------------------------
    */
    'schedule_enabled' => filter_var(env('DTE_S3_SCHEDULE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

];
