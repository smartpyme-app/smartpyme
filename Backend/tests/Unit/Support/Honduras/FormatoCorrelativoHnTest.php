<?php

namespace Tests\Unit\Support\Honduras;

use App\Support\Honduras\FormatoCorrelativoHn;
use PHPUnit\Framework\TestCase;

final class FormatoCorrelativoHnTest extends TestCase
{
    public function test_format_with_emision(): void
    {
        $this->assertSame('001-001-01-00000439', FormatoCorrelativoHn::format('01', 439));
        $this->assertSame('001-001-20-00000001', FormatoCorrelativoHn::format('20', 1));
    }

    public function test_format_without_emision_returns_raw(): void
    {
        $this->assertSame('439', FormatoCorrelativoHn::format(null, 439));
        $this->assertSame('439', FormatoCorrelativoHn::format('', '439'));
    }
}
