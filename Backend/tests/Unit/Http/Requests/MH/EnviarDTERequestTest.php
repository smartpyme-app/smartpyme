<?php

namespace Tests\Unit\Http\Requests\MH;

use App\Http\Requests\MH\GenerarDTEAnuladoRequest;
use PHPUnit\Framework\TestCase;

class EnviarDTERequestTest extends TestCase
{
    public function test_tipo_dte_from_dte_array_via_generar_helper(): void
    {
        $tipo = GenerarDTEAnuladoRequest::tipoDteFromDte([
            'identificacion' => ['tipoDte' => '01'],
        ]);

        $this->assertSame('01', $tipo);
    }
}
