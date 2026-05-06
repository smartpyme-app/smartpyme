<?php

/**
 * Valores por defecto del reporte Estado de resultados NIIF PYMES (clasificación por prefijo de código).
 *
 * - Por instancia: use variables de entorno (listas separadas por coma).
 * - Por empresa: columna JSON `estado_resultados_prefijos` en `contabilidad_configuracion`
 *   (ver migración). Tiene prioridad sobre este archivo cuando está definida.
 */
return [
    'estado_resultados_prefijos' => [
        'cogs' => array_values(array_filter(array_map(static function (string $p): string {
            return preg_replace('/\D+/', '', trim($p));
        }, explode(',', env('ER_PREFIJOS_COGS', '4101'))))),
        'gasto_venta' => array_values(array_filter(array_map(static function (string $p): string {
            return preg_replace('/\D+/', '', trim($p));
        }, explode(',', env('ER_PREFIJOS_GASTO_VENTA', '4102'))))),
        'gasto_admin' => array_values(array_filter(array_map(static function (string $p): string {
            return preg_replace('/\D+/', '', trim($p));
        }, explode(',', env('ER_PREFIJOS_GASTO_ADMIN', '4103'))))),
    ],
];
