<?php

namespace Tests\Unit\Services\Inventario;

use App\Services\Inventario\LoteEnDescripcionDte;
use Tests\TestCase;

class LoteEnDescripcionDteTest extends TestCase
{
    public function test_extrae_lote_de_la_descripcion_de_mercantil(): void
    {
        $descripcion = '100000084-Gel Íntimo Femenino ph 3.8 200 ml | Lote: 5A26YA09 Cantidad Lote: 3 | Fecha fabricación: 2025-11-12 | Fecha Caducidad: 2028-11-12';

        $lote = LoteEnDescripcionDte::extraer($descripcion);

        $this->assertSame('5A26YA09', $lote['numero_lote']);
        $this->assertSame(3.0, $lote['cantidad']);
        $this->assertSame('2025-11-12', $lote['fecha_fabricacion']);
        $this->assertSame('2028-11-12', $lote['fecha_vencimiento']);
        $this->assertSame('100000084-Gel Íntimo Femenino ph 3.8 200 ml', $lote['descripcion']);
    }

    public function test_tolera_espacios_extra_en_el_numero_de_lote(): void
    {
        $descripcion = '100005870-Cetaphil Barra Dermolimpiadora 127 mg | Lote: 3075  Cantidad Lote: 5 | Fecha fabricación: 2025-03-11 | Fecha Caducidad: 2028-10-31';

        $lote = LoteEnDescripcionDte::extraer($descripcion);

        $this->assertSame('3075', $lote['numero_lote']);
        $this->assertSame(5.0, $lote['cantidad']);
    }

    public function test_descripcion_sin_lote_queda_igual(): void
    {
        $this->assertNull(LoteEnDescripcionDte::extraer('Paracetamol 500 mg'));
        $this->assertSame('Paracetamol 500 mg', LoteEnDescripcionDte::descripcionProducto('Paracetamol 500 mg'));
    }
}
