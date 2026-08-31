<?php

namespace Tests\Unit\Imports;

use App\Imports\Concerns\ParsesProductoExcelColumns;
use PHPUnit\Framework\TestCase;

final class ParsesProductoExcelColumnsTest extends TestCase
{
    private object $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new class {
            use ParsesProductoExcelColumns;

            public function impuesto($v): ?array
            {
                return $this->parseImpuestoExcelValue($v);
            }

            public function marca($v): ?string
            {
                return $this->parseMarcaExcelValue($v);
            }

            public function subcategoria($v): ?string
            {
                return $this->parseSubcategoriaExcelValue($v);
            }

            public function subcategoriaFromRow(array $row): ?string
            {
                return $this->parseSubcategoriaExcelValue($row['subcategoria'] ?? null);
            }
        };
    }

    public function test_impuesto_vacio_es_null(): void
    {
        $this->assertNull($this->parser->impuesto(null));
        $this->assertNull($this->parser->impuesto(''));
        $this->assertNull($this->parser->impuesto('   '));
    }

    public function test_impuesto_porcentaje_numerico(): void
    {
        $this->assertSame(['tipo' => 'porcentaje', 'valor' => 15.0], $this->parser->impuesto(15));
        $this->assertSame(['tipo' => 'porcentaje', 'valor' => 15.0], $this->parser->impuesto('15'));
        $this->assertSame(['tipo' => 'porcentaje', 'valor' => 15.0], $this->parser->impuesto('15%'));
        $this->assertSame(['tipo' => 'porcentaje', 'valor' => 12.5], $this->parser->impuesto('12,5'));
    }

    public function test_impuesto_por_nombre(): void
    {
        $this->assertSame(
            ['tipo' => 'nombre', 'valor' => 'IVA 15%'],
            $this->parser->impuesto('IVA 15%')
        );
    }

    public function test_marca_vacia_es_null(): void
    {
        $this->assertNull($this->parser->marca(null));
        $this->assertNull($this->parser->marca(''));
        $this->assertNull($this->parser->marca('  '));
        $this->assertSame('Nike', $this->parser->marca(' Nike '));
    }

    public function test_subcategoria_ausente_o_vacia_es_null(): void
    {
        $this->assertNull($this->parser->subcategoria(null));
        $this->assertNull($this->parser->subcategoria(''));
        $this->assertNull($this->parser->subcategoria('  '));
    }

    public function test_subcategoria_nombre_trim(): void
    {
        $this->assertSame('Bebidas', $this->parser->subcategoria(' Bebidas '));
    }

    public function test_fila_sin_clave_subcategoria_no_falla(): void
    {
        $this->assertNull($this->parser->subcategoriaFromRow([
            'nombre' => 'Producto',
            'categoria' => 'Alimentos',
        ]));
    }
}
