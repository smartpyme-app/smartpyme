<?php

namespace Tests\Unit\Ventas;

use App\Models\Ventas\Venta;
use Tests\TestCase;

class VentaListadoSelectTest extends TestCase
{
    public function test_select_listado_no_incluye_columnas_dte_pesadas(): void
    {
        $sql = Venta::query()->selectListado()->toSql();

        $this->assertStringNotContainsString('dte', strtolower($sql));
        $this->assertStringNotContainsString('credito', strtolower($sql));
        $this->assertStringContainsString('sello_mh', $sql);
        $this->assertStringContainsString('condicion', $sql);
    }
}
