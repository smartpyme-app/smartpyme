<?php

namespace Tests\Unit\Services\Audit;

use App\Services\Audit\AuditPresentationService;
use PHPUnit\Framework\TestCase;

class AuditPresentationServiceTest extends TestCase
{
    public function test_describe_created_venta(): void
    {
        $svc = new AuditPresentationService();
        $text = $svc->describe(
            'created',
            'App\\Models\\Ventas\\Venta',
            ['correlativo' => 'FAC-001'],
            'David'
        );
        $this->assertSame('David creó Venta #FAC-001', $text);
    }

    public function test_describe_updated_compra_unknown_type(): void
    {
        $svc = new AuditPresentationService();
        $text = $svc->describe('updated', 'App\\Models\\Foo\\Bar', ['id' => 99], null);
        $this->assertSame('Sistema actualizó Bar #99', $text);
    }
}
