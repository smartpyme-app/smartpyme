<?php

namespace Tests\Unit\Services\Dte;

use App\Services\Dte\DteJsonHelper;
use PHPUnit\Framework\TestCase;

class DteJsonHelperTest extends TestCase
{
    public function test_extract_sello_recibido_from_common_keys(): void
    {
        $this->assertSame('abc', DteJsonHelper::extractSelloRecibido(['selloRecibido' => 'abc']));
        $this->assertSame('def', DteJsonHelper::extractSelloRecibido(['sello' => 'def']));
        $this->assertNull(DteJsonHelper::extractSelloRecibido(['identificacion' => []]));
    }
}
