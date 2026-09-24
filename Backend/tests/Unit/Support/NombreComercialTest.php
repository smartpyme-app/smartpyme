<?php

namespace Tests\Unit\Support;

use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Ventas\Clientes\Cliente;
use App\Support\NombreComercial;
use Tests\TestCase;

class NombreComercialTest extends TestCase
{
    public function test_anexa_el_nombre_comercial_entre_parentesis(): void
    {
        $this->assertSame('Legal SA (Tienda Centro)', NombreComercial::anexar('Legal SA', 'Tienda Centro'));
    }

    public function test_omite_el_parentesis_si_no_hay_nombre_comercial(): void
    {
        $this->assertSame('Legal SA', NombreComercial::anexar('Legal SA', '  '));
        $this->assertSame('Legal SA', NombreComercial::anexar('Legal SA', null));
    }

    public function test_no_repite_el_nombre_si_es_igual_al_legal(): void
    {
        $this->assertSame('Legal SA', NombreComercial::anexar('Legal SA', 'legal sa'));
    }

    public function test_cliente_y_proveedor_lo_usan_en_documentos(): void
    {
        $cliente = new Cliente([
            'tipo' => 'Empresa',
            'nombre_empresa' => 'Legal SA',
            'nombre_comercial' => 'Tienda Centro',
        ]);
        $proveedor = new Proveedor([
            'tipo' => 'Persona',
            'nombre' => 'Ana',
            'apellido' => 'Rivas',
            'nombre_comercial' => 'Ana Express',
        ]);

        $this->assertSame('Legal SA', $cliente->nombreLegal());
        $this->assertSame('Legal SA (Tienda Centro)', $cliente->nombreParaDocumento());
        $this->assertSame('Ana Rivas', $proveedor->nombreLegal());
        $this->assertSame('Ana Rivas (Ana Express)', $proveedor->nombreParaDocumento());
    }
}
