<?php

namespace Tests\Unit\Ventas\Cotizaciones;

use App\Support\Ventas\CotizacionFormatoNuevoDefaults;
use PHPUnit\Framework\TestCase;

final class CotizacionFormatoNuevoDefaultsTest extends TestCase
{
    public function test_normalizar_color_rechaza_valores_invalidos(): void
    {
        $this->assertSame('#0a2f7a', CotizacionFormatoNuevoDefaults::normalizarColor('red'));
        $this->assertSame('#112233', CotizacionFormatoNuevoDefaults::normalizarColor('#112233'));
    }

    public function test_defaults_de_texto_no_vacios(): void
    {
        $this->assertNotSame('', trim(CotizacionFormatoNuevoDefaults::carta()));
        $this->assertNotSame('', trim(CotizacionFormatoNuevoDefaults::terminos()));
        $this->assertNotSame('', trim(CotizacionFormatoNuevoDefaults::compromisos()));
    }
}