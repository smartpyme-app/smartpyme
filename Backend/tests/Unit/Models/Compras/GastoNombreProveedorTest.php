<?php

namespace Tests\Unit\Models\Compras;

use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Proveedores\Proveedor;
use Tests\TestCase;

class GastoNombreProveedorTest extends TestCase
{
    public function test_nombre_proveedor_usa_relacion_precargada_cuando_existe(): void
    {
        $proveedor = new Proveedor([
            'tipo' => 'Empresa',
            'nombre_empresa' => 'Proveedor Precargado SA',
        ]);
        $proveedor->id = 1;

        $gasto = new Gasto(['id_proveedor' => 1]);
        $gasto->setRelation('proveedor', $proveedor);

        $this->assertSame('Proveedor Precargado SA', $gasto->nombre_proveedor);
    }

    public function test_nombre_proveedor_sin_id_proveedor_es_consumidor_final(): void
    {
        $gasto = new Gasto(['id_proveedor' => null]);
        $gasto->setRelation('proveedor', null);

        $this->assertSame('Consumidor Final', $gasto->nombre_proveedor);
    }

}
