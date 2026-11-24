<?php

namespace Tests\Unit\Support\FacturacionElectronica;

use App\Support\FacturacionElectronica\ClaveComprobanteCr;
use PHPUnit\Framework\TestCase;

final class ClaveComprobanteCrConsecutivoTest extends TestCase
{
    public function test_consecutivo_desde_clave_limpia(): void
    {
        $prefijo = '506171126003101569075'; // 21
        $consec = '00100001010000001451'; // 20
        $sufijo = '112345678'; // 9
        $clave = $prefijo.$consec.$sufijo;
        self::assertSame(50, strlen($clave));
        self::assertSame($consec, ClaveComprobanteCr::consecutivoDesdeClave($clave));
    }

    public function test_consecutivo_tolera_espacios_y_guiones(): void
    {
        $clave = '506171126003101569075-00100001010000001451-1 12345678';
        self::assertSame('00100001010000001451', ClaveComprobanteCr::consecutivoDesdeClave($clave));
    }

    public function test_consecutivo_null_si_no_hay_50_tras_normalizar(): void
    {
        self::assertNull(ClaveComprobanteCr::consecutivoDesdeClave('12345'));
    }

    public function test_identificador_para_reporte_prioriza_numero_control(): void
    {
        self::assertSame(
            '001-00001-01-0000001451',
            ClaveComprobanteCr::identificadorParaReporte('00100001010000001451', 99)
        );
        self::assertSame('99', ClaveComprobanteCr::identificadorParaReporte(null, 99));
        self::assertSame('99', ClaveComprobanteCr::identificadorParaReporte('', 99));
    }
}
