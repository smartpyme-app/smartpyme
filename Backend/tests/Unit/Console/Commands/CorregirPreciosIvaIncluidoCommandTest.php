<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\CorregirPreciosIvaIncluidoCommand;
use PHPUnit\Framework\TestCase;

final class CorregirPreciosIvaIncluidoCommandTest extends TestCase
{
    public function test_porcentaje_iva_usa_producto_si_esta_definido(): void
    {
        $this->assertSame(10.0, CorregirPreciosIvaIncluidoCommand::porcentajeIvaEfectivo(10.0, 13.0));
        $this->assertSame(13.0, CorregirPreciosIvaIncluidoCommand::porcentajeIvaEfectivo(0.0, 13.0));
        $this->assertSame(13.0, CorregirPreciosIvaIncluidoCommand::porcentajeIvaEfectivo(null, 13.0));
    }

    public function test_precios_corregidos_ejemplo_ticket_50_con_13_porciento(): void
    {
        $out = CorregirPreciosIvaIncluidoCommand::preciosCorregidos(50.0, 13.0);

        $this->assertSame(44.25, $out['precio']);
        $this->assertSame(44.25, $out['precio_sin_iva']);
        $this->assertSame(50.0, $out['precio_con_iva']);
    }

    public function test_necesita_correccion_caso_mal_cargado(): void
    {
        $this->assertTrue(CorregirPreciosIvaIncluidoCommand::necesitaCorreccion(50.0, 50.0, 56.5, 13.0));
        $this->assertTrue(CorregirPreciosIvaIncluidoCommand::necesitaCorreccion(50.0, null, null, 13.0));
    }

    public function test_no_corregir_exento_o_precio_cero(): void
    {
        $this->assertFalse(CorregirPreciosIvaIncluidoCommand::necesitaCorreccion(50.0, 50.0, 50.0, 0.0));
        $this->assertFalse(CorregirPreciosIvaIncluidoCommand::necesitaCorreccion(0.0, null, null, 13.0));
    }
}
