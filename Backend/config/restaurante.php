<?php

return [
    /*
    | Side-effects no críticos (cache ticket HTML + notificación/log) tras commit.
    | La integridad de negocio NO depende de esto; MariaDB sigue siendo SoT.
    */
    'side_effects_enabled' => env('RESTAURANTE_SIDE_EFFECTS_ENABLED', true),

    'side_effects_queue' => env('RESTAURANTE_SIDE_EFFECTS_QUEUE', 'default'),

    /** TTL del HTML de ticket en cache (segundos). Miss → re-render sync en GET imprimir. */
    'ticket_cache_ttl' => (int) env('RESTAURANTE_TICKET_CACHE_TTL', 300),

    /*
    | Realtime (Fase 6): hints de UI vía broadcasting. No es SoT.
    | Con BROADCAST_DRIVER=null/log los eventos se aceptan pero no hay WS.
    | Reverb es opcional: ver FASE6_REPORT.md.
    */
    'realtime_enabled' => env('RESTAURANTE_REALTIME_ENABLED', true),
];
