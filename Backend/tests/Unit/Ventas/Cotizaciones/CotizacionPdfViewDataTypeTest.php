<?php

namespace Tests\Unit\Ventas\Cotizaciones;

use App\Http\Controllers\Api\Ventas\Cotizaciones\CotizacionesController;
use App\Models\CotizacionVenta;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regresión Unificado: el PDF de cotización trabaja con CotizacionVenta,
 * no con App\Models\Ventas\Venta (alias Cotizacion del controller legacy).
 */
class CotizacionPdfViewDataTypeTest extends TestCase
{
    public function test_cotizacion_pdf_view_data_acepta_cotizacion_venta(): void
    {
        $method = new ReflectionMethod(CotizacionesController::class, 'cotizacionPdfViewData');
        $type = $method->getParameters()[0]->getType();

        $this->assertNotNull($type);
        $this->assertSame(CotizacionVenta::class, $type->getName());
    }

    public function test_generar_doc_pasa_pdf_data_a_la_vista(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/app/Http/Controllers/Api/Ventas/Cotizaciones/CotizacionesController.php'
        );

        $this->assertStringContainsString('cotizacionPdfViewData($venta)', $source);
        $this->assertMatchesRegularExpression(
            "/loadView\([^,]+,\s*\\\$pdfData\)/",
            $source
        );
    }
}
