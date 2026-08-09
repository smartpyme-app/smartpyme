<?php

/**
 * Actor CLI independiente para tests de concurrencia.
 * Uso: php concurrent_actor.php <input.json> <output.json>
 *
 * input: { action, barrier_file, expected_actors, user_id, ...params }
 */

use App\Http\Controllers\Api\Restaurante\ComandaController;
use App\Http\Controllers\Api\Restaurante\OrdenDetalleController;
use App\Http\Controllers\Api\Restaurante\PedidoRestauranteController;
use App\Http\Controllers\Api\Restaurante\SesionMesaController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$inFile = $argv[1] ?? null;
$outFile = $argv[2] ?? null;
if (! $inFile || ! $outFile) {
    fwrite(STDERR, "usage: concurrent_actor.php in.json out.json\n");
    exit(2);
}

$input = json_decode(file_get_contents($inFile), true, 512, JSON_THROW_ON_ERROR);

$backend = dirname(__DIR__, 3); // tests/Support/Restaurante → Backend
require $backend . '/vendor/autoload.php';
$app = require $backend . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Conexión fresca de este proceso (no compartida con el padre).
DB::purge('mysql');
DB::reconnect('mysql');

$barrier = $input['barrier_file'] ?? null;
$expected = (int) ($input['expected_actors'] ?? 1);
if ($barrier) {
    $fp = fopen($barrier, 'c+');
    if ($fp) {
        flock($fp, LOCK_EX);
        $n = (int) trim((string) stream_get_contents($fp));
        $n++;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) $n);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline) {
            $cur = (int) trim((string) @file_get_contents($barrier));
            if ($cur >= $expected) {
                break;
            }
            usleep(20000);
        }
    }
}

$user = User::findOrFail((int) $input['user_id']);
Auth::login($user);

$action = $input['action'] ?? '';
$result = ['ok' => false, 'action' => $action];

try {
    switch ($action) {
        case 'abrir_mesa':
            $req = Request::create('/api/restaurante/sesiones-mesa', 'POST', [
                'mesa_id' => (int) $input['mesa_id'],
                'num_comensales' => (int) ($input['num_comensales'] ?? 2),
            ]);
            $req->setUserResolver(fn () => $user);
            $resp = app(SesionMesaController::class)->store($req);
            $result = [
                'ok' => $resp->getStatusCode() < 400,
                'status' => $resp->getStatusCode(),
                'body' => json_decode($resp->getContent(), true),
            ];
            break;

        case 'agregar_item':
            $req = Request::create(
                '/api/restaurante/sesiones-mesa/' . (int) $input['sesion_id'] . '/items',
                'POST',
                [
                    'producto_id' => (int) $input['producto_id'],
                    'cantidad' => (float) ($input['cantidad'] ?? 1),
                    'notas' => $input['notas'] ?? null,
                ]
            );
            $req->setUserResolver(fn () => $user);
            $resp = app(OrdenDetalleController::class)->store($req, (int) $input['sesion_id']);
            $result = [
                'ok' => $resp->getStatusCode() < 400,
                'status' => $resp->getStatusCode(),
                'body' => json_decode($resp->getContent(), true),
            ];
            break;

        case 'enviar_comanda':
            $req = Request::create(
                '/api/restaurante/sesiones-mesa/' . (int) $input['sesion_id'] . '/comandas',
                'POST',
                []
            );
            $req->setUserResolver(fn () => $user);
            $resp = app(ComandaController::class)->store($req, (int) $input['sesion_id']);
            $result = [
                'ok' => $resp->getStatusCode() < 400,
                'status' => $resp->getStatusCode(),
                'body' => json_decode($resp->getContent(), true),
            ];
            break;

        case 'confirmar_pedido':
            $body = [];
            if (! empty($input['id_bodega'])) {
                $body['id_bodega'] = (int) $input['id_bodega'];
            }
            $req = Request::create(
                '/api/restaurante/pedidos/' . (int) $input['pedido_id'] . '/confirmar',
                'PUT',
                $body
            );
            $req->setUserResolver(fn () => $user);
            $resp = app(PedidoRestauranteController::class)->confirmar($req, (int) $input['pedido_id']);
            $result = [
                'ok' => $resp->getStatusCode() < 400,
                'status' => $resp->getStatusCode(),
                'body' => json_decode($resp->getContent(), true),
            ];
            break;

        default:
            $result = ['ok' => false, 'error' => 'unknown action'];
    }
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'error' => $e->getMessage(),
        'exception' => class_basename($e),
    ];
}

file_put_contents($outFile, json_encode($result, JSON_THROW_ON_ERROR));
exit(($result['ok'] ?? false) ? 0 : 1);
