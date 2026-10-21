<?php

namespace App\Services;

/**
 * Extrae talla del final del nombre (p. ej. "ZW-3687P FUCSIA L" → base + L).
 */
final class WooCommerceTallaNombreParser
{
    /** @var string[] */
    private const TALLAS = [
        'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '2XL', '3XL', '4XL', '5XL',
        'UNICA', 'ÚNICA',
    ];

    /**
     * @return array{base: string, talla: string|null}
     */
    public function parse(string $nombre): array
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre) ?? '');
        if ($nombre === '') {
            return ['base' => '', 'talla' => null];
        }

        $parts = explode(' ', $nombre);
        if (count($parts) < 2) {
            return ['base' => $nombre, 'talla' => null];
        }

        $last = strtoupper($parts[count($parts) - 1]);
        if (!$this->looksLikeTalla($last)) {
            return ['base' => $nombre, 'talla' => null];
        }

        array_pop($parts);

        return [
            'base' => trim(implode(' ', $parts)),
            'talla' => $last,
        ];
    }

    public function looksLikeTalla(string $token): bool
    {
        $token = strtoupper(trim($token));
        if ($token === '') {
            return false;
        }
        if (in_array($token, self::TALLAS, true)) {
            return true;
        }
        // Una letra (L, M, S) o número de talla (38, 40)
        if (preg_match('/^[A-Z]{1,2}$/u', $token)) {
            return true;
        }
        if (preg_match('/^\d{1,2}$/', $token)) {
            return true;
        }

        return false;
    }
}
