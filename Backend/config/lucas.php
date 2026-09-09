<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lucas AI Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración de conexión con el servicio de IA "Lucas" (FastAPI en
    | Python). Reemplaza la integración directa con AWS Bedrock.
    |
    */

    'base_url' => env('LUCAS_API_URL', 'http://localhost:8000'),
    'api_key'  => env('LUCAS_API_KEY', ''),
    'timeout'  => env('LUCAS_API_TIMEOUT', 120),
];
