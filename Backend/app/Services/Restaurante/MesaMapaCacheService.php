<?php

namespace App\Services\Restaurante;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cache corto del mapa GET /mesas.
 * Redis preferido si el store redis responde; si no, cache default.
 * No es fuente de verdad: miss/fallo → consulta DB.
 */
class MesaMapaCacheService
{
    public const TTL_SECONDS = 3;

    public const VERSION_PREFIX = 'rest:mapa:ver:';

    public const PAYLOAD_PREFIX = 'rest:mapa:p:';

    public function remember(
        int $idEmpresa,
        ?int $idSucursal,
        mixed $activoFilter,
        callable $callback,
    ): array {
        $key = $this->payloadKey($idEmpresa, $idSucursal, $activoFilter);

        try {
            $cached = $this->store()->get($key);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (Throwable) {
            // fallthrough a DB
        }

        $payload = $callback();
        if (! is_array($payload)) {
            return [];
        }

        try {
            $this->store()->put($key, $payload, self::TTL_SECONDS);
        } catch (Throwable) {
            // cache write opcional
        }

        return $payload;
    }

    /**
     * Invalida el mapa de toda la empresa (todas sucursales/filtros) vía bump de versión.
     */
    public function invalidateEmpresa(int $idEmpresa): void
    {
        if ($idEmpresa <= 0) {
            return;
        }

        try {
            $verKey = self::VERSION_PREFIX.$idEmpresa;
            $store = $this->store();
            if (! $store->has($verKey)) {
                $store->forever($verKey, 2);

                return;
            }
            $store->increment($verKey);
        } catch (Throwable) {
            // invalidación best-effort
        }
    }

    public function payloadKey(int $idEmpresa, ?int $idSucursal, mixed $activoFilter): string
    {
        $ver = 1;
        try {
            $ver = (int) ($this->store()->get(self::VERSION_PREFIX.$idEmpresa) ?: 1);
        } catch (Throwable) {
            $ver = 1;
        }

        $suc = $idSucursal !== null ? (string) $idSucursal : 'all';
        if ($activoFilter === null || $activoFilter === '') {
            $act = 'all';
        } else {
            $act = filter_var($activoFilter, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $act = $act === null ? 'all' : ($act ? '1' : '0');
        }

        return self::PAYLOAD_PREFIX."{$ver}:{$idEmpresa}:{$suc}:{$act}";
    }

    private function store(): Repository
    {
        static $resolved = null;
        if ($resolved instanceof Repository) {
            return $resolved;
        }

        try {
            $redis = Cache::store('redis');
            // probe: get no-op key
            $redis->get('__rest_mapa_probe__');
            $resolved = $redis;
        } catch (Throwable) {
            $resolved = Cache::store();
        }

        return $resolved;
    }
}
