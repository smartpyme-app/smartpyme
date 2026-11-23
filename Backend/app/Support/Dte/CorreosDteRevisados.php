<?php

namespace App\Support\Dte;

use Illuminate\Support\Facades\Cache;

/**
 * Recuerda los correos que ya se revisaron aunque no hayan creado un DteDocument
 * (por ejemplo, el mismo DTE llegó en otro correo y se descartó como duplicado).
 * Sin esto, la sincronización de cada hora volvía a descargar esos correos durante 30 días.
 */
class CorreosDteRevisados
{
    /** Algo más que la ventana de búsqueda de la sincronización (--dias=30). */
    private const DIAS = 32;

    public static function marcar(int $accountId, string $messageId): void
    {
        Cache::put(self::clave($accountId, $messageId), true, now()->addDays(self::DIAS));
    }

    /**
     * @param  array<int, string>  $messageIds
     * @return array<string, true>
     */
    public static function revisados(int $accountId, array $messageIds): array
    {
        $out = [];
        foreach ($messageIds as $id) {
            if (Cache::has(self::clave($accountId, $id))) {
                $out[$id] = true;
            }
        }

        return $out;
    }

    /** Id del correo de Gmail: en Costa Rica el id del documento es "<id del correo>-<clave>". */
    public static function idCorreo(string $emailMessageId): string
    {
        return explode('-', $emailMessageId, 2)[0];
    }

    private static function clave(int $accountId, string $messageId): string
    {
        return "dte-correo-revisado:{$accountId}:{$messageId}";
    }
}
