<?php
/**
 * Carbon 3: una fecha de pago pasada debe contar en negativo.
 * Run: php Backend/tests/Unit/suscripcion-dias-signo.check.php
 */
require __DIR__ . '/../../vendor/autoload.php';

use App\Models\Suscripcion;
use Carbon\Carbon;

$suscripcion = new Suscripcion();
$metodo = new ReflectionMethod($suscripcion, 'diasConSigno');
$metodo->setAccessible(true);

$hoy = Carbon::parse('2026-09-30 15:00:00');
$vencida = Carbon::parse('2026-09-20 08:00:00');
$vigente = Carbon::parse('2026-10-10 08:00:00');

$diasVencidos = $metodo->invoke($suscripcion, $hoy, $vencida);
$diasVigentes = $metodo->invoke($suscripcion, $hoy, $vigente);

if ($diasVencidos !== -10) {
    fwrite(STDERR, "vencida esperaba -10, obtuvo {$diasVencidos}\n");
    exit(1);
}
if ($diasVigentes !== 10) {
    fwrite(STDERR, "vigente esperaba 10, obtuvo {$diasVigentes}\n");
    exit(1);
}

$comando = file_get_contents(__DIR__ . '/../../app/Console/Commands/VerificarSuscripcion.php');
if (!str_contains($comando, 'diffInDays(') || !str_contains($comando, 'true')) {
    fwrite(STDERR, "el comando de verificación no pide días absolutos\n");
    exit(1);
}

echo "ok\n";
