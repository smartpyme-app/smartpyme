<?php

namespace App\Helpers;

use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Facades\Log;

class DNS1D
{
    /**
     * Generate barcode PNG image (base64 encoded)
     * Compatible with milon/barcode API
     * 
     * @param string $code The code to generate
     * @param string $type Barcode type (C39, C39+, etc.)
     * @param int $widthFactor Width factor
     * @param int $height Height in pixels
     * @param array $color RGB color array [r, g, b]
     * @param bool $base64 Whether to return base64 encoded string
     * @return string Base64 encoded PNG image
     */
    public static function getBarcodePNG($code, $type = 'C39', $widthFactor = 2, $height = 30, $color = [0, 0, 0], $base64 = false)
    {
        $code = trim((string) $code);
        if ($code === '') {
            return '';
        }

        try {
            // Map milon/barcode types to picqer types (using string constants)
            $typeMap = [
                'C39' => 'C39',
                'C39+' => 'C39+',
                'C39E' => 'C39E',
                'C39E+' => 'C39E+',
                'C93' => 'C93',
                'S25' => 'S25',
                'S25+' => 'S25+',
                'I25' => 'I25',
                'I25+' => 'I25+',
                'C128' => 'C128',
                'C128A' => 'C128A',
                'C128B' => 'C128B',
                'C128C' => 'C128C',
                'EAN2' => 'EAN2',
                'EAN5' => 'EAN5',
                'EAN8' => 'EAN8',
                'EAN13' => 'EAN13',
                'UPCA' => 'UPCA',
                'UPCE' => 'UPCE',
                'MSI' => 'MSI',
                'MSI+' => 'MSI+',
                'POSTNET' => 'POSTNET',
                'PLANET' => 'PLANET',
                'CODABAR' => 'CODABAR',
                'CODE11' => 'CODE11',
            ];

            $mappedType = $typeMap[$type] ?? 'C39';

            // Ensure color array has 3 elements
            $foregroundColor = count($color) >= 3 ? [$color[0], $color[1], $color[2]] : [0, 0, 0];

            $png = self::renderPng($code, $mappedType, $widthFactor, $height, $foregroundColor);
            if ($png !== '') {
                return base64_encode($png);
            }

            // ponytail: C39 solo mayúsculas; códigos/SKU con minúsculas → C128 (mismo dato escaneable).
            if (self::isCode39Family($mappedType)) {
                $upper = strtoupper($code);
                if ($upper !== $code) {
                    $png = self::renderPng($upper, $mappedType, $widthFactor, $height, $foregroundColor);
                    if ($png !== '') {
                        return base64_encode($png);
                    }
                }
                $png = self::renderPng($code, 'C128', $widthFactor, $height, $foregroundColor);
                if ($png !== '') {
                    return base64_encode($png);
                }
            }

            Log::error('Barcode generation error: no se pudo generar imagen', [
                'type' => $mappedType,
                'code_len' => strlen($code),
            ]);

            return '';
        } catch (\Exception $e) {
            Log::error('Barcode generation error: '.$e->getMessage());

            return '';
        }
    }

    private static function isCode39Family(string $mappedType): bool
    {
        return str_starts_with($mappedType, 'C39');
    }

    private static function renderPng(
        string $code,
        string $mappedType,
        int $widthFactor,
        int $height,
        array $foregroundColor
    ): string {
        try {
            $generator = new BarcodeGeneratorPNG();

            return $generator->getBarcode($code, $mappedType, $widthFactor, $height, $foregroundColor);
        } catch (\Throwable) {
            return '';
        }
    }
}

