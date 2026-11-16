<?php

namespace Tests\Unit\Services\Audit;

use App\Models\Compras\Compra;
use App\Services\Audit\AuditQueryService;
use ReflectionMethod;
use Tests\TestCase;

class AuditQueryServiceCompraReferenceTest extends TestCase
{
    public function test_compra_references_query_does_not_use_correlativo_column(): void
    {
        $service = new AuditQueryService();
        $method = new ReflectionMethod($service, 'loadReferencesForType');
        $method->setAccessible(true);

        if (! class_exists(Compra::class)) {
            $this->markTestSkipped('Compra model unavailable');
        }

        $result = $method->invoke($service, Compra::class, [PHP_INT_MAX]);

        $this->assertIsArray($result);
    }
}
