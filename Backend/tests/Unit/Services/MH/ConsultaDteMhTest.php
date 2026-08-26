<?php

namespace Tests\Unit\Services\MH;

use App\Services\MH\ConsultaDteMh;
use PHPUnit\Framework\TestCase;

class ConsultaDteMhTest extends TestCase
{
    public function test_payload_usa_campos_oficiales_y_nit_sin_guiones(): void
    {
        $payload = ConsultaDteMh::payload('0614-123456-789-0', '01', 'AAAA1111-BBBB-CCCC-DDDD-EEEEEEEEEEEE');

        $this->assertSame([
            'nitEmisor' => '06141234567890',
            'tdte' => '01',
            'codigoGeneracion' => 'AAAA1111-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
        ], $payload);
    }

    public function test_respuesta_oficial_expone_sello_val_para_la_ui(): void
    {
        $adaptada = ConsultaDteMh::adaptarRespuesta([
            'estado' => 'PROCESADO',
            'selloRecibido' => 'SELLO-OFICIAL-123',
            'codigoGeneracion' => 'AAAA1111-BBBB-CCCC-DDDD-EEEEEEEEEEEE',
        ]);

        $this->assertSame('SELLO-OFICIAL-123', $adaptada['selloRecibido']);
        $this->assertSame('SELLO-OFICIAL-123', $adaptada['selloVal']);
    }

    public function test_sin_sello_no_inventa_sello_val(): void
    {
        $adaptada = ConsultaDteMh::adaptarRespuesta([
            'estado' => 'RECHAZADO',
            'descripcionMsg' => 'No existe',
        ]);

        $this->assertArrayNotHasKey('selloVal', $adaptada);
    }
}
