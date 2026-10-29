<?php

namespace Tests\Unit\Http\Requests\MH;

use App\Http\Requests\MH\GenerarDTEAnuladoRequest;
use PHPUnit\Framework\TestCase;

class GenerarDTEAnuladoRequestTest extends TestCase
{
    public function test_tipo_dte_from_dte_array(): void
    {
        $tipo = GenerarDTEAnuladoRequest::tipoDteFromDte([
            'identificacion' => ['tipoDte' => '03'],
        ]);

        $this->assertSame('03', $tipo);
    }

    public function test_tipo_dte_from_dte_json_string(): void
    {
        $json = json_encode(['identificacion' => ['tipoDte' => '01']], JSON_THROW_ON_ERROR);
        $tipo = GenerarDTEAnuladoRequest::tipoDteFromDte($json);

        $this->assertSame('01', $tipo);
    }
}
