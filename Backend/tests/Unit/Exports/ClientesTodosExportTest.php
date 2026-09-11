<?php

namespace Tests\Unit\Exports;

use App\Exports\ClientesTodosExport;
use App\Models\Ventas\Clientes\Cliente;
use Tests\TestCase;

class ClientesTodosExportTest extends TestCase
{
    public function test_tipo_es_la_primera_columna(): void
    {
        $headings = (new ClientesTodosExport())->headings();

        $this->assertSame('Tipo', $headings[0]);
    }

    public function test_map_pone_el_tipo_primero(): void
    {
        $cliente = new Cliente();
        $cliente->tipo = 'Empresa';
        $cliente->nombre = null;
        $cliente->apellido = null;
        $cliente->nombre_empresa = 'ACME SA';
        $cliente->codigo_cliente = 'C-1';
        $cliente->dui = '00000000-0';
        $cliente->nit = '0614-000000-000-0';
        $cliente->ncr = '12345-6';
        $cliente->giro = 'Comercio';
        $cliente->tipo_contribuyente = 'Grande';
        $cliente->empresa_direccion = 'Calle 1';
        $cliente->direccion = 'Otra';
        $cliente->municipio = 'San Salvador';
        $cliente->distrito = 'Centro';
        $cliente->departamento = 'San Salvador';
        $cliente->pais = null;
        $cliente->fecha_cumpleanos = null;
        $cliente->telefono = '2222-2222';
        $cliente->correo = 'acme@test.com';
        $cliente->nota = null;
        $cliente->enable = 1;

        $fila = (new ClientesTodosExport())->map($cliente);

        $this->assertSame('Empresa', $fila[0]);
        $this->assertSame('ACME SA', $fila[3]);
        $this->assertSame('Calle 1', $fila[10]);
        $this->assertSame('Activo', $fila[count($fila) - 1]);
    }
}
