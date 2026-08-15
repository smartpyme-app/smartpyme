<?php

namespace Tests\Unit\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Api\Inventario\ProductosController;
use App\Http\Controllers\Api\Inventario\TrasladosController;
use App\Imports\TrasladosImport;
use App\Models\Inventario\Traslado;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class TrasladoMasivoDocumentoEntregaTest extends TestCase
{
    public function test_traslado_guarda_id_grupo_para_documento_de_entrega(): void
    {
        $fillable = (new ReflectionClass(Traslado::class))->getDefaultProperties()['fillable'] ?? [];

        $this->assertContains(
            'id_grupo',
            $fillable,
            'El traslado masivo debe agrupar líneas con id_grupo para poder reimprimir el documento'
        );
    }

    public function test_traslado_masivo_asigna_y_devuelve_id_grupo(): void
    {
        $source = $this->methodSource(ProductosController::class, 'trasladoMasivo');

        $this->assertStringContainsString('id_grupo', $source);
        $this->assertStringContainsString("'id_grupo'", $source);
        $this->assertStringContainsString('Str::uuid()', $source);
    }

    public function test_importacion_masiva_devuelve_id_grupo(): void
    {
        $source = $this->methodSource(ProductosController::class, 'importarTrasladosMasivos');

        $this->assertStringContainsString('id_grupo', $source);
        $this->assertStringContainsString('Str::uuid()', $source);
    }

    public function test_importador_acepta_id_grupo_y_lo_asigna_al_traslado(): void
    {
        $ctor = (new ReflectionClass(TrasladosImport::class))->getConstructor();
        $params = array_map(fn ($p) => $p->getName(), $ctor->getParameters());

        $this->assertContains('idGrupo', $params);

        $source = $this->methodSource(TrasladosImport::class, 'model');
        $this->assertStringContainsString('id_grupo', $source);
    }

    public function test_existe_pdf_de_grupo_para_documento_de_entrega(): void
    {
        $this->assertTrue(method_exists(TrasladosController::class, 'generarPdfGrupo'));

        $source = $this->methodSource(TrasladosController::class, 'generarPdfGrupo');
        $this->assertStringContainsString("where('id_grupo'", $source);
        $this->assertStringContainsString('traslado-grupo-pdf', $source);
    }

    private function methodSource(string $class, string $method): string
    {
        $ref = new ReflectionMethod($class, $method);
        $lines = file($ref->getFileName());

        return implode('', array_slice(
            $lines,
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1
        ));
    }
}
