<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\RestauranteIdempotencyKey;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Idempotencia HTTP opcional vía header Idempotency-Key.
 * Persistencia en MariaDB (unique + lock). No usa Redis como fuente de verdad.
 */
class RestauranteIdempotencyService
{
    public const TTL_HOURS = 24;

    /**
     * @param  callable(): JsonResponse  $callback
     */
    public function run(string $operation, Request $request, callable $callback): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            return $callback();
        }

        if (strlen($key) > 128 || ! preg_match('/^[A-Za-z0-9._:-]+$/', $key)) {
            return response()->json(['error' => 'Idempotency-Key inválida'], 400);
        }

        /** @var User|null $user */
        $user = auth()->user();
        if (! $user || ! $user->id_empresa) {
            return response()->json(['error' => 'Usuario sin empresa asociada'], 400);
        }

        $this->purgeExpired();

        $idEmpresa = (int) $user->id_empresa;
        $userId = (int) $user->id;

        // Hasta 2 intentos: si la key vencida se limpia en el conflicto unique, reintentar create.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $record = null;

            try {
                $record = RestauranteIdempotencyKey::create([
                    'id_empresa' => $idEmpresa,
                    'user_id' => $userId,
                    'operation' => $operation,
                    'idempotency_key' => $key,
                    'status' => 'processing',
                    'expires_at' => now()->addHours(self::TTL_HOURS),
                ]);
            } catch (UniqueConstraintViolationException) {
                $replay = $this->responseFromExisting($idEmpresa, $userId, $operation, $key);
                if ($replay !== null) {
                    return $replay;
                }

                continue;
            }

            try {
                $response = $callback();
                $status = $response->getStatusCode();
                $body = $response->getContent();

                $record->update([
                    'status' => 'completed',
                    'response_code' => $status,
                    'response_body' => $body !== false ? $body : null,
                ]);

                return $response;
            } catch (Throwable $e) {
                $record->delete();
                throw $e;
            }
        }

        return response()->json(['error' => 'Idempotency-Key en conflicto'], 409);
    }

    /**
     * @return JsonResponse|null null = registro vencido/ausente limpiado → reintentar create
     */
    private function responseFromExisting(int $idEmpresa, int $userId, string $operation, string $key): ?JsonResponse
    {
        return DB::transaction(function () use ($idEmpresa, $userId, $operation, $key) {
            $existing = RestauranteIdempotencyKey::where('id_empresa', $idEmpresa)
                ->where('user_id', $userId)
                ->where('operation', $operation)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                return null;
            }

            if ($existing->expires_at && $existing->expires_at->isPast()) {
                $existing->delete();

                return null;
            }

            if ($existing->status === 'processing') {
                return response()->json([
                    'error' => 'La misma operación está en progreso. Reintente en unos segundos.',
                ], 409);
            }

            $payload = json_decode((string) $existing->response_body, true);
            if (! is_array($payload)) {
                $payload = ['raw' => $existing->response_body];
            }

            return response()->json($payload, (int) ($existing->response_code ?: 200));
        });
    }

    private function purgeExpired(): void
    {
        // ponytail: purge best-effort; ceiling = tablas grandes → cron dedicado
        RestauranteIdempotencyKey::where('expires_at', '<', now())->limit(100)->delete();
    }
}
