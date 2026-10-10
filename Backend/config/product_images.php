<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de Laravel Filesystem para imágenes de productos
    |--------------------------------------------------------------------------
    */
    'disk' => env('AWS_PRODUCT_IMAGES_DISK', 's3_productos'),

    /*
    |--------------------------------------------------------------------------
    | URL pública base (sin barra final)
    |--------------------------------------------------------------------------
    */
    'url' => rtrim((string) env('AWS_PRODUCT_IMAGES_URL', ''), '/'),

    /*
    |--------------------------------------------------------------------------
    | Carpeta local legacy (padre de productos/*.jpg)
    |--------------------------------------------------------------------------
    | Por defecto: public_path('img'). En VPS suele ser la carpeta img del
    | document root (ej. /var/www/api/img), no Backend/public/img.
    */
    'local_root' => env('PRODUCT_IMAGES_LOCAL_ROOT'),

];
