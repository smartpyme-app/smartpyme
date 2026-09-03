<?php

namespace Tests\Unit\Services\Contabilidad\Partidas;

use App\Services\Contabilidad\Partidas\ReglaIngresoVenta;
use PHPUnit\Framework\TestCase;

class ReglaIngresoVentaTest extends TestCase
{
    public function test_venta_pendiente_usa_forma_de_pago_no_cxc(): void
    {
        $venta = (object) ['estado' => 'Pendiente', 'forma_pago' => 'Efectivo'];

        $this->assertSame('forma_pago', ReglaIngresoVenta::origenCuentaDebe($venta));
    }

    public function test_venta_pagada_usa_forma_de_pago(): void
    {
        $venta = (object) ['estado' => 'Pagada', 'forma_pago' => 'Efectivo'];

        $this->assertSame('forma_pago', ReglaIngresoVenta::origenCuentaDebe($venta));
    }

    public function test_cierre_del_dia_omite_ids_que_ya_tienen_partida(): void
    {
        $pendientes = ReglaIngresoVenta::idsSinPartida([10, 11, 12, 11], [11]);

        $this->assertSame([10, 12], $pendientes);
    }

    public function test_cierre_del_dia_sin_ya_contabilizados_deja_todos(): void
    {
        $this->assertSame([1, 2], ReglaIngresoVenta::idsSinPartida([1, 2], []));
    }
}
