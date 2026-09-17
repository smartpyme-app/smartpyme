<?php

namespace Tests\Unit\Contabilidad;

use App\Services\Contabilidad\ActivosReportesService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ActivosReportesServiceTest extends TestCase
{
    public function test_rechaza_tipo_invalido(): void
    {
        $service = new ActivosReportesService;

        $this->expectException(InvalidArgumentException::class);
        $service->generar('no-existe', 1, []);
    }
}
