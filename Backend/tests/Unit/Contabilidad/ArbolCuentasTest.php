<?php

namespace Tests\Unit\Contabilidad;

use App\Services\Contabilidad\ArbolCuentas;
use PHPUnit\Framework\TestCase;

class ArbolCuentasTest extends TestCase
{
    private array $catalogo = [
        ['id' => 1, 'id_cuenta_padre' => null],
        ['id' => 2, 'id_cuenta_padre' => 1],
        ['id' => 3, 'id_cuenta_padre' => 2],
        ['id' => 4, 'id_cuenta_padre' => 1],
        ['id' => 9, 'id_cuenta_padre' => null],
    ];

    public function test_id_requerido_rechaza_all_y_vacio(): void
    {
        $this->assertNull(ArbolCuentas::idRequerido('all'));
        $this->assertNull(ArbolCuentas::idRequerido(''));
        $this->assertNull(ArbolCuentas::idRequerido(null));
        $this->assertSame(12, ArbolCuentas::idRequerido('12'));
        $this->assertSame(12, ArbolCuentas::idRequerido(12));
    }

    public function test_hoja_es_solo_ese_id(): void
    {
        $this->assertSame([3], ArbolCuentas::idsDelArbol($this->catalogo, 3));
    }

    public function test_padre_incluye_hijas_y_nietas(): void
    {
        $ids = ArbolCuentas::idsDelArbol($this->catalogo, 1);
        sort($ids);
        $this->assertSame([1, 2, 3, 4], $ids);
    }

    public function test_raiz_inexistente_es_vacio(): void
    {
        $this->assertSame([], ArbolCuentas::idsDelArbol($this->catalogo, 99));
    }

    public function test_acepta_objetos_como_eloquent(): void
    {
        $cuentas = array_map(fn ($r) => (object) $r, $this->catalogo);
        $this->assertSame([3], ArbolCuentas::idsDelArbol($cuentas, 3));
    }
}
