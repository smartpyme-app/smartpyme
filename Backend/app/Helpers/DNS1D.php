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
            
            $generator = new BarcodeGeneratorPNG();
            $barcode = $generator->getBarcode($code, $mappedType, $widthFactor, $height, $foregroundColor);
            
            // Always return base64 encoded
            return base64_encode($barcode);
        } catch (\Exception $e) {
            // Fallback: return empty image
            Log::error('Barcode generation error: ' . $e->getMessage());
            return '';
        }
    }
}

