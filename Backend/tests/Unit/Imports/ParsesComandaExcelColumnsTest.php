<?php

namespace Tests\Unit\Imports;

use App\Imports\Concerns\ParsesComandaExcelColumns;
use PHPUnit\Framework\TestCase;

final class ParsesComandaExcelColumnsTest extends TestCase
{
    private object $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new class {
            use ParsesComandaExcelColumns;

            public function genera($v): bool
            {
                return $this->parseGeneraComanda($v);
            }

            public function destino($v, bool $g): string
            {
                return $this->parseDestinoComanda($v, $g);
            }
        };
    }

    public function test_parse_genera_comanda(): void
    {
        $this->assertTrue($this->parser->genera('Si'));
        $this->assertTrue($this->parser->genera('sí'));
        $this->assertTrue($this->parser->genera(1));
        $this->assertFalse($this->parser->genera('No'));
        $this->assertFalse($this->parser->genera(''));
        $this->assertFalse($this->parser->genera(null));
    }

    public function test_parse_destino_comanda(): void
    {
        $this->assertSame('cocina', $this->parser->destino(null, false));
        $this->assertSame('barra', $this->parser->destino('Barra', true));
        $this->assertSame('ambos', $this->parser->destino('ambos', true));
        $this->assertSame('cocina', $this->parser->destino('', true));
    }
}
