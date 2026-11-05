<?php

namespace Tests\Unit\Helpers;

use App\Helpers\DNS1D;
use PHPUnit\Framework\TestCase;

class DNS1DFallbackTest extends TestCase
{
    public function test_lowercase_sku_generates_barcode_with_c39_request(): void
    {
        $pngB64 = DNS1D::getBarcodePNG('prod-h', 'C39', 2, 50);

        $this->assertNotSame('', $pngB64);
        $this->assertNotFalse(base64_decode($pngB64, true));
    }
}
