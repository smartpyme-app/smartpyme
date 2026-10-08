<?php

namespace Tests\Unit\Services\FacturacionElectronica\CostaRica;

use App\Support\FacturacionElectronica\CorreoDestinoDte;
use PHPUnit\Framework\TestCase;

final class CostaRicaFeCorreoDestinoTest extends TestCase
{
    public function test_correo_escrito_reemplaza_al_del_registro_y_vacio_lo_conserva(): void
    {
        self::assertSame('otro@cliente.com', CorreoDestinoDte::resolver('cliente@empresa.com', ' otro@cliente.com '));
        self::assertSame('cliente@empresa.com', CorreoDestinoDte::resolver('cliente@empresa.com', ''));
        self::assertSame('cliente@empresa.com', CorreoDestinoDte::resolver('cliente@empresa.com', null));
        self::assertSame('cliente@empresa.com', CorreoDestinoDte::resolver('cliente@empresa.com', '   '));
    }
}
