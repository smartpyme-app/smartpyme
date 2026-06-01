<?php

namespace App\Services\Dte;

class DteParserService
{
    /**
     * Parse DTE JSON from MH format to normalized array.
     *
     * @param string $jsonContent
     * @return array{dte_uuid: string, dte_type: string, dte_number: string, emission_date: string, total_amount: float, issuer_nit: string, issuer_name: string, receiver_nit: ?string, receiver_name: ?string, items: array}
     * @throws \InvalidArgumentException
     */
    public function parseFromJson(string $jsonContent): array
    {
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        $identificacion = $data['identificacion'] ?? [];
        $emisor = $data['emisor'] ?? [];
        $receptor = $data['receptor'] ?? [];
        $resumen = $data['resumen'] ?? [];
        $cuerpoDocumento = $data['cuerpoDocumento'] ?? [];

        $dteUuid = $identificacion['codigoGeneracion'] ?? $identificacion['codigoGeneracion'] ?? '';
        $dteType = str_pad((string) ($identificacion['tipoDte'] ?? '01'), 2, '0', STR_PAD_LEFT);
        $dteNumber = $identificacion['numeroControl'] ?? $identificacion['numeroDocumento'] ?? '';
        $emissionDate = $identificacion['fecEmi'] ?? date('Y-m-d');

        $totalAmount = 0;
        if (isset($resumen['totalPagar'])) {
            $totalAmount = (float) $resumen['totalPagar'];
        } elseif (isset($resumen['montoTotalOperacion'])) {
            $totalAmount = (float) $resumen['montoTotalOperacion'];
        }

        $items = [];
        foreach ($cuerpoDocumento as $item) {
            $items[] = [
                'descripcion' => $item['descripcion'] ?? '',
                'cantidad' => (float) ($item['cantidad'] ?? 0),
                'precioUni' => (float) ($item['precioUni'] ?? 0),
                'ventaTotal' => (float) ($item['ventaTotal'] ?? 0),
            ];
        }

        return [
            'dte_uuid' => $dteUuid,
            'dte_type' => $dteType,
            'dte_number' => $dteNumber,
            'emission_date' => $emissionDate,
            'total_amount' => $totalAmount,
            'issuer_nit' => $emisor['nit'] ?? $emisor['numDocumento'] ?? '',
            'issuer_name' => $emisor['nombre'] ?? $emisor['nombreComercial'] ?? '',
            'receiver_nit' => $receptor['nit'] ?? $receptor['numDocumento'] ?? null,
            'receiver_name' => $receptor['nombre'] ?? $receptor['nombreComercial'] ?? null,
            'items' => $items,
            'raw' => $data,
        ];
    }

    /**
     * Validate DTE structure has required fields.
     *
     * @param array $dteData Parsed DTE data
     * @return array{valid: bool, errors: array}
     */
    public function validateStructure(array $dteData): array
    {
        $errors = [];

        if (empty($dteData['dte_uuid'])) {
            $errors[] = 'Falta codigoGeneracion (dte_uuid)';
        }
        if (empty($dteData['dte_type'])) {
            $errors[] = 'Falta tipoDte';
        }
        if (empty($dteData['issuer_nit'])) {
            $errors[] = 'Falta NIT del emisor';
        }
        if (empty($dteData['emission_date'])) {
            $errors[] = 'Falta fecha de emisión';
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }
}
