<?php

namespace Tests\Unit\Exports;

use App\Exports\ProductosExport;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProductosExportVentasLimitadoTest extends TestCase
{
    public function test_oculta_costo_y_ganancia_para_ventas_limitado(): void
    {
        $user = new User();
        $user->tipo = 'Ventas Limitado';
        Auth::setUser($user);

        $headings = (new ProductosExport())->headings();
        $this->assertNotContains('Costo', $headings);
        $this->assertNotContains('Ganancia', $headings);
        $this->assertContains('Precio sin IVA', $headings);

        Auth::logout();

        $headings = (new ProductosExport())->headings();
        $this->assertContains('Costo', $headings);
        $this->assertContains('Ganancia', $headings);
        $this->assertContains('Precio sin IVA', $headings);
    }
}
