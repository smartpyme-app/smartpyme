<?php

namespace App\Services\GiftCards;

use App\Models\GiftCards\GiftCard;
use RuntimeException;

class GiftCardCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function __construct(
        private string $prefix = 'GC',
        private int $totalLength = 12,
        private int $maxAttempts = 10
    ) {
    }

    public function generate(): string
    {
        $suffixLength = $this->totalLength - strlen($this->prefix);
        if ($suffixLength < 1) {
            throw new RuntimeException('totalLength must exceed prefix length.');
        }

        $suffix = '';
        for ($i = 0; $i < $suffixLength; $i++) {
            $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $this->prefix . $suffix;
    }

    public function generateUnique(int $idEmpresa): string
    {
        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            $code = $this->generate();
            $exists = GiftCard::withoutGlobalScope('empresa')
                ->where('id_empresa', $idEmpresa)
                ->where('codigo', $code)
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique gift card code.');
    }
}
