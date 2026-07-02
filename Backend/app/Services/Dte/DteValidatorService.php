<?php

namespace App\Services\Dte;

use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;

class DteValidatorService
{
    protected int $maxAgeYears = 1;

    /**
     * @param array $dteData Parsed DTE data from DteDocumentParseService
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
            $errors[] = "Identificación del receptor ({$receiverNit}) no coincide con la empresa ({$tenantNitNorm})";
        }

        $pais = $dteData['pais'] ?? FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR;
        if ($pais === FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR) {
            $raw = $dteData['raw'] ?? [];
            $selloRecibido = DteJsonHelper::extractSelloRecibido($raw);
            if (empty($selloRecibido)) {
                $errors[] = 'Falta sello de recepción del MH';
            }
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

    protected function normalizeNit(?string $nit): string
    {
        if (empty($nit)) {
            return '';
        }

        return preg_replace('/[^0-9A-Za-z]/', '', $nit);
    }
}
