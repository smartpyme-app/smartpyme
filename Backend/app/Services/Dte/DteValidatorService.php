<?php

namespace App\Services\Dte;

class DteValidatorService
{
    /**
     * Maximum age of DTE in years (reject if older).
     */
    protected int $maxAgeYears = 1;

    /**
     * Validate DTE for the tenant.
     *
     * @param array $dteData Parsed DTE data from DteParserService
     * @param string $tenantNit NIT of the empresa (receiver must match)
     * @return array{valid: bool, errors: array}
     */
    public function validate(array $dteData, string $tenantNit): array
    {
        $errors = [];

        $parser = new DteParserService();
        $structure = $parser->validateStructure($dteData);
        if (!$structure['valid']) {
            return $structure;
        }

        $receiverNit = $this->normalizeNit($dteData['receiver_nit'] ?? '');
        $tenantNitNorm = $this->normalizeNit($tenantNit);

        if (!empty($receiverNit) && !empty($tenantNitNorm) && $receiverNit !== $tenantNitNorm) {
            $errors[] = "NIT receptor ({$receiverNit}) no coincide con la empresa ({$tenantNitNorm})";
        }

        $raw = $dteData['raw'] ?? [];
        $selloRecibido = $raw['selloRecibido'] ?? $raw['sello'] ?? null;
        if (empty($selloRecibido)) {
            $errors[] = 'Falta sello de recepción del MH';
        }

        $emissionDate = $dteData['emission_date'] ?? null;
        if ($emissionDate) {
            $emission = \Carbon\Carbon::parse($emissionDate);
            $maxDate = now()->subYears($this->maxAgeYears);
            if ($emission->lt($maxDate)) {
                $errors[] = "Fecha de emisión ({$emissionDate}) excede el rango permitido (máximo {$this->maxAgeYears} año atrás)";
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }

    /**
     * Normalize NIT for comparison (remove dashes, spaces, etc).
     */
    protected function normalizeNit(?string $nit): string
    {
        if (empty($nit)) {
            return '';
        }
        return preg_replace('/[^0-9A-Za-z]/', '', $nit);
    }
}
