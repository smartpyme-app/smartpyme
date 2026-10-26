<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\ActualizarCorreoEmisorDteCommand;
use PHPUnit\Framework\TestCase;

final class ActualizarCorreoEmisorDteCommandTest extends TestCase
{
    public function test_no_cambia_si_el_correo_ya_es_el_objetivo(): void
    {
        $dte = ['emisor' => ['correo' => 'Facturacion@Empresa.com']];
        $out = ActualizarCorreoEmisorDteCommand::parchearCorreoEmisor($dte, 'facturacion@empresa.com');

        $this->assertFalse($out['changed']);
        $this->assertSame('Facturacion@Empresa.com', $out['data']['emisor']['correo']);
    }

    public function test_actualiza_si_el_correo_es_distinto(): void
    {
        $dte = ['emisor' => ['correo' => 'viejo@gmail.com', 'nit' => '0614']];
        $out = ActualizarCorreoEmisorDteCommand::parchearCorreoEmisor($dte, 'nuevo@gmail.com');

        $this->assertTrue($out['changed']);
        $this->assertSame('nuevo@gmail.com', $out['data']['emisor']['correo']);
        $this->assertSame('0614', $out['data']['emisor']['nit']);
    }

    public function test_crea_emisor_si_falta(): void
    {
        $out = ActualizarCorreoEmisorDteCommand::parchearCorreoEmisor([], 'a@b.com');

        $this->assertTrue($out['changed']);
        $this->assertSame('a@b.com', $out['data']['emisor']['correo']);
    }
}
