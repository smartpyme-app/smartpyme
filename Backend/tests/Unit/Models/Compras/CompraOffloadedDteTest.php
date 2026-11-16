<?php

namespace Tests\Unit\Models\Compras;

use App\Models\Compras\Compra;
use App\Models\Concerns\HasOffloadedDte;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CompraOffloadedDteTest extends TestCase
{
    public function test_compra_lee_dte_offloaded_como_venta(): void
    {
        $this->assertContains(
            HasOffloadedDte::class,
            class_uses_recursive(Compra::class),
            'Compra debe leer el JSON de S3; si no, Ver PDF queda en blanco y se reemite el mismo codigoGeneracion'
        );

        $source = file_get_contents((new ReflectionClass(Compra::class))->getFileName());
        $this->assertStringContainsString(
            "'dte_en_s3'",
            $source,
            'El listado oculta dte cuando hay dte_s3_key; el front necesita dte_en_s3 para recargar'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/function getDteAttribute\s*\(/',
            $source,
            'El accessor local tapa el del trait y no busca en S3'
        );
    }

    public function test_open_dte_no_reemite_si_ya_hay_sello_o_json_en_s3(): void
    {
        $path = dirname(__DIR__, 5) . '/Frontend/src/app/views/compras/compras.component.ts';
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertNotFalse(preg_match('/openDTE\([^)]*\)\s*\{([\s\S]*?)\n    \}\n/', $source, $m));
        $openDte = $m[1];

        $this->assertStringContainsString(
            'dte_en_s3 || compra.sello_mh',
            $openDte,
            'Si hay sello o JSON en S3, hay que recargar la compra antes de abrir el modal'
        );
        $this->assertStringContainsString(
            '!this.compra.sello_mh',
            $openDte,
            'No reemitir un DTE que Hacienda ya aceptó (codigoGeneracion duplicado)'
        );
    }
}
