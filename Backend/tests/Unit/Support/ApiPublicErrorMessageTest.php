<?php

namespace Tests\Unit\Support;

use App\Support\ApiPublicErrorMessage;
use PHPUnit\Framework\TestCase;

class ApiPublicErrorMessageTest extends TestCase
{
    public function test_oculta_sqlstate_en_sanitize(): void
    {
        $raw = 'SQLSTATE[22003]: Out of range value for column `precio_con_iva` (Connection: mysql, SQL: insert into `detalles_venta`...)';
        $safe = ApiPublicErrorMessage::sanitizeString($raw);

        $this->assertStringNotContainsString('SQLSTATE', $safe);
        $this->assertStringNotContainsString('insert into', $safe);
        $this->assertStringContainsString('límite', $safe);
    }
}
