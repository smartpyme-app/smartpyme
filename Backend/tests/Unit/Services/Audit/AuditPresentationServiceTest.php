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

    public function test_describe_venta_updated_uses_document_reference(): void
    {
        $svc = new AuditPresentationService();
        $svc->setDocumentReferences(['App\\Models\\Ventas\\Venta:10' => 'FAC-001234']);
        $text = $svc->describe(
            'updated',
            'App\\Models\\Ventas\\Venta',
            ['estado' => 'Pagada'],
            'Silvia Maribel Ramos Juarez',
            ['estado' => 'Pendiente'],
            10
        );
        $this->assertSame('Silvia Maribel Ramos Juarez actualizó Venta #FAC-001234', $text);
    }

    public function test_describe_ajuste_with_product_name(): void
    {
        $svc = new AuditPresentationService();
        $svc->setProductNames([42 => 'Café molido']);
        $text = $svc->describe(
            'updated',
            'App\\Models\\Inventario\\Ajuste',
            ['id_producto' => 42],
            'Juan'
        );
        $this->assertSame('Juan actualizó Ajuste de inventario «Café molido»', $text);
    }

    public function test_describe_updated_compra_unknown_type(): void
    {
        $svc = new AuditPresentationService();
        $text = $svc->describe('updated', 'App\\Models\\Foo\\Bar', ['total' => 100], null, [], 99);
        $this->assertSame('Sistema actualizó Bar #99', $text);
    }
}
