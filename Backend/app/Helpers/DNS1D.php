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
     * @param bool $base64 Whether to return base64 encoded string (legacy milon API; siempre se devuelve base64)
     * @param bool $includeLabel Dibuja el código legible debajo de las barras (incluido al guardar PNG)
     * @return string Base64 encoded PNG image
     */
    public static function getBarcodePNG($code, $type = 'C39', $widthFactor = 2, $height = 30, $color = [0, 0, 0], $base64 = false, $includeLabel = false)
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
                return self::encodePng($png, $code, $includeLabel);
            }

            // ponytail: C39 solo mayúsculas; códigos/SKU con minúsculas → C128 (mismo dato escaneable).
            if (self::isCode39Family($mappedType)) {
                $upper = strtoupper($code);
                if ($upper !== $code) {
                    $png = self::renderPng($upper, $mappedType, $widthFactor, $height, $foregroundColor);
                    if ($png !== '') {
                        return self::encodePng($png, $code, $includeLabel);
                    }
                }
                $png = self::renderPng($code, 'C128', $widthFactor, $height, $foregroundColor);
                if ($png !== '') {
                    return self::encodePng($png, $code, $includeLabel);
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

    private static function encodePng(string $png, string $label, bool $includeLabel): string
    {
        if ($includeLabel) {
            $png = self::appendLabelToPng($png, $label);
        }

        return base64_encode($png);
    }

    /** @return string PNG binario (sin base64) */
    public static function appendLabelToPng(string $pngBinary, string $label): string
    {
        if ($pngBinary === '' || ! extension_loaded('gd')) {
            return $pngBinary;
        }

        $label = trim($label);
        if ($label === '') {
            return $pngBinary;
        }

        $bars = @imagecreatefromstring($pngBinary);
        if ($bars === false) {
            return $pngBinary;
        }

        $barsW = imagesx($bars);
        $barsH = imagesy($bars);
        $padX = 12;
        $padY = 10;
        $fontSize = 18;
        [$textW, $textH] = self::measureLabelText($label, $fontSize);
        $canvasW = max($barsW, $textW + (2 * $padX));
        $canvasH = $barsH + $padY + $textH + $padY;

        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        if ($canvas === false) {
            imagedestroy($bars);

            return $pngBinary;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $bars, (int) (($canvasW - $barsW) / 2), 0, 0, 0, $barsW, $barsH);
        self::drawLabelText(
            $canvas,
            $label,
            (int) max($padX, ($canvasW - $textW) / 2),
            $barsH + $padY + $textH,
            $black,
            $fontSize
        );

        ob_start();
        imagepng($canvas);
        $out = ob_get_clean() ?: $pngBinary;
        imagedestroy($bars);
        imagedestroy($canvas);

        return $out;
    }

    /** @return array{0: int, 1: int} ancho y alto del texto */
    private static function measureLabelText(string $label, int $fontSize): array
    {
        $fontPath = self::labelFontPath();
        if ($fontPath !== null && function_exists('imagettfbbox')) {
            $box = imagettfbbox($fontSize, 0, $fontPath, $label);
            if ($box !== false) {
                return [
                    (int) abs($box[2] - $box[0]),
                    (int) abs($box[7] - $box[1]),
                ];
            }
        }

        $font = 5;
        $charW = imagefontwidth($font);
        $charH = imagefontheight($font);

        return [strlen($label) * $charW, $charH];
    }

    private static function drawLabelText($canvas, string $label, int $x, int $yBaseline, int $color, int $fontSize): void
    {
        $fontPath = self::labelFontPath();
        if ($fontPath !== null && function_exists('imagettftext')) {
            imagettftext($canvas, $fontSize, 0, $x, $yBaseline, $color, $fontPath, $label);

            return;
        }

        $font = 5;
        $y = $yBaseline - imagefontheight($font);
        imagestring($canvas, $font, $x, $y, $label, $color);
        imagestring($canvas, $font, $x + 1, $y, $label, $color);
    }

    private static function labelFontPath(): ?string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved !== '' ? $resolved : null;
        }

        foreach ([
            __DIR__.'/../../vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf',
            __DIR__.'/../../vendor/mpdf/mpdf/ttfonts/DejaVuSans-Bold.ttf',
        ] as $path) {
            if (is_readable($path)) {
                $resolved = realpath($path) ?: $path;

                return $resolved;
            }
        }

        $resolved = '';

        return null;
    }
}

