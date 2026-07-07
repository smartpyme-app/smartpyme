<?php

namespace App\Helpers;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Log;
use Throwable;

class DNS2D
{
    /**
     * Generate QR code PNG image (base64 encoded).
     * Compatible with milon/barcode API used in vistas DTE/FE.
     *
     * ponytail: simple-qrcode PNG exige Imagick; sin él devolvía cadena vacía.
     * Fallback: rasteriza la matriz QR con ext-gd (disponible en la mayoría de servidores).
     *
     * @param  array<int, int>  $color  RGB (reservado; salida monocromática)
     * @return string Base64 PNG o cadena vacía si falla
     */
    public static function getBarcodePNG($code, $type = 'QRCODE', $size = 10, $margin = 1, $color = [0, 0, 0], $base64 = false)
    {
        if ($type !== 'QRCODE') {
            Log::warning('DNS2D: solo QRCODE está soportado', ['type' => $type]);

            return '';
        }

        $text = trim((string) $code);
        if ($text === '') {
            return '';
        }

        // Escala compatible con vistas existentes (size 10 ≈ 200 px).
        $pixelSize = max(100, (int) $size * 20);
        $marginPx = max(0, (int) $margin);

        try {
            if (extension_loaded('imagick')) {
                $pngBinary = self::pngViaImagick($text, $pixelSize, $marginPx);
                if ($pngBinary !== '') {
                    return base64_encode($pngBinary);
                }
            }

            if (extension_loaded('gd')) {
                $pngBinary = self::pngViaGdMatrix($text, $pixelSize, $marginPx);
                if ($pngBinary !== '') {
                    return base64_encode($pngBinary);
                }
            }

            throw new \RuntimeException('No hay backend disponible (imagick o gd).');
        } catch (Throwable $e) {
            Log::error('QR code generation error: '.$e->getMessage(), [
                'code_len' => strlen($text),
            ]);

            return '';
        }
    }

    private static function pngViaImagick(string $text, int $pixelSize, int $marginPx): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($pixelSize, $marginPx),
            new ImagickImageBackEnd
        );
        $writer = new Writer($renderer);

        return $writer->writeString($text);
    }

    private static function pngViaGdMatrix(string $text, int $pixelSize, int $marginPx): string
    {
        $qr = Encoder::encode($text, ErrorCorrectionLevel::L());
        $matrix = $qr->getMatrix();
        $modules = $matrix->getWidth();
        if ($modules <= 0) {
            return '';
        }

        $inner = max(1, $pixelSize - (2 * $marginPx));
        $modulePx = max(1, (int) floor($inner / $modules));
        $imgSize = ($modulePx * $modules) + (2 * $marginPx);

        $img = imagecreatetruecolor($imgSize, $imgSize);
        if ($img === false) {
            return '';
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        for ($y = 0; $y < $modules; ++$y) {
            for ($x = 0; $x < $modules; ++$x) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }
                $x1 = $marginPx + ($x * $modulePx);
                $y1 = $marginPx + ($y * $modulePx);
                imagefilledrectangle($img, $x1, $y1, $x1 + $modulePx - 1, $y1 + $modulePx - 1, $black);
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean() ?: '';
        imagedestroy($img);

        return $png;
    }
}
