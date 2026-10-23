<?php

namespace Tests\Unit\Support\FacturacionElectronica;

use App\Support\FacturacionElectronica\ClaveComprobanteCr;
use PHPUnit\Framework\TestCase;

final class ClaveComprobanteCrTest extends TestCase
{
    public function test_clave_con_letra_sobrevive_para_qr_y_archivo(): void
    {
        $clave = '506221026003101A00001'.str_repeat('1', 29);

        self::assertSame(50, strlen($clave));
        self::assertSame($clave, ClaveComprobanteCr::paraQrYArchivo($clave));
        self::assertSame($clave, ClaveComprobanteCr::paraQrYArchivo(substr($clave, 0, 21).'-'.substr($clave, 21)));
    }
}
