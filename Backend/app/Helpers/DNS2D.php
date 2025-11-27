<?php

namespace App\Helpers;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;

class DNS2D
{
    /**
     * Generate QR code PNG image (base64 encoded)
     * Compatible with milon/barcode API
     * 
     * @param string $code The code to generate
     * @param string $type QR code type (QRCODE)
     * @param int $size Size in pixels
     * @param int $margin Margin
     * @param array $color RGB color array [r, g, b]
     * @param bool $base64 Whether to return base64 encoded string
     * @return string Base64 encoded PNG image
     */
    public static function getBarcodePNG($code, $type = 'QRCODE', $size = 10, $margin = 1, $color = [0, 0, 0], $base64 = false)
    {
        try {
            if ($type !== 'QRCODE') {
                throw new \Exception('Only QRCODE type is supported for 2D barcodes');
            }
            
            // Convert size (simple-qrcode uses different scale)
            // size 10 in milon = approximately 200px in simple-qrcode
            $qrSize = max(100, $size * 20);
            
            // Generate QR code
            $qrCode = QrCode::format('png')
                ->size($qrSize)
                ->margin($margin)
                ->generate($code);
            
            // simple-qrcode returns binary data, encode to base64
            $base64String = base64_encode($qrCode);
            
            return $base64String;
        } catch (\Exception $e) {
            // Fallback: return empty image
            Log::error('QR code generation error: ' . $e->getMessage());
            return '';
        }
    }
}

