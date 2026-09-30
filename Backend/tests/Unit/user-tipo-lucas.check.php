<?php
/**
 * Lucas solo acepta user_type "Administrador".
 * Run: php Backend/tests/Unit/user-tipo-lucas.check.php
 */
require __DIR__ . '/../../vendor/autoload.php';

use App\Models\User;

$casos = [
    [['super_admin'], 'Super Administrador', 'Administrador'],
    [['admin'], 'Usuario', 'Administrador'],
    [[], 'Super Administrador', 'Administrador'],
    [[], 'Administrador', 'Administrador'],
    [['usuario_ventas'], 'Vendedor', 'Vendedor'],
    [[], null, 'Usuario'],
    [[], '  ', 'Usuario'],
];

foreach ($casos as [$roles, $tipo, $esperado]) {
    $obtuvo = User::tipoLucas($roles, $tipo);
    if ($obtuvo !== $esperado) {
        $tipoTexto = var_export($tipo, true);
        fwrite(STDERR, "roles=" . json_encode($roles) . " tipo={$tipoTexto} esperaba {$esperado}, obtuvo {$obtuvo}\n");
        exit(1);
    }
}

$chat = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Api/Chat/ChatController.php');
$ai = file_get_contents(__DIR__ . '/../../app/Services/AIService.php');
if (substr_count($chat, 'tipoParaLucas()') !== 2 || !str_contains($ai, 'tipoParaLucas()')) {
    fwrite(STDERR, "el payload de Lucas no usa tipoParaLucas\n");
    exit(1);
}

echo "ok\n";
