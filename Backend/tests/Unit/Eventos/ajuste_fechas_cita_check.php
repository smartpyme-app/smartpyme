<?php

require __DIR__ . '/../../../app/Support/Eventos/AjusteFechasCita.php';

use App\Support\Eventos\AjusteFechasCita;

$sinRepetir = AjusteFechasCita::aplicar([
    'frecuencia' => '',
    'frecuencia_fin' => '0000-00-00 00:00:00',
    'inicio' => '2026-10-05 23:00:00',
    'fin' => '2026-10-05 00:00:00',
]);

$ok = $sinRepetir['frecuencia'] === null
    && $sinRepetir['frecuencia_fin'] === null
    && $sinRepetir['fin'] === '2026-10-06 00:00:00';

$mismaHora = AjusteFechasCita::aplicar([
    'frecuencia' => 'DAILY',
    'frecuencia_fin' => '2026-10-10',
    'inicio' => '2026-10-05 11:00:00',
    'fin' => '2026-10-05 12:00:00',
]);

$ok = $ok
    && $mismaHora['frecuencia'] === 'DAILY'
    && $mismaHora['frecuencia_fin'] === '2026-10-10'
    && $mismaHora['fin'] === '2026-10-05 12:00:00';

if (!$ok) {
    fwrite(STDERR, json_encode([$sinRepetir, $mismaHora], JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}

echo "ok\n";
