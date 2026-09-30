<?php

namespace Tests\Unit\Services\Funcionalidades;

use App\Services\Funcionalidades\FuncionalidadJerarquia;
use PHPUnit\Framework\TestCase;

class FuncionalidadJerarquiaTest extends TestCase
{
    public function test_activar_hijo_prende_el_padre(): void
    {
        $resultado = FuncionalidadJerarquia::aplicar($this->catalogo(), 2, true);

        $this->assertTrue($this->activoDe($resultado, 1));
        $this->assertTrue($this->activoDe($resultado, 2));
        $this->assertFalse($this->activoDe($resultado, 3));
    }

    public function test_apagar_padre_apaga_los_hijos(): void
    {
        $inicio = $this->catalogo(true, true, true);
        $resultado = FuncionalidadJerarquia::aplicar($inicio, 1, false);

        $this->assertFalse($this->activoDe($resultado, 1));
        $this->assertFalse($this->activoDe($resultado, 2));
        $this->assertFalse($this->activoDe($resultado, 3));
    }

    public function test_prender_padre_no_prende_hijos(): void
    {
        $resultado = FuncionalidadJerarquia::aplicar($this->catalogo(), 1, true);

        $this->assertTrue($this->activoDe($resultado, 1));
        $this->assertFalse($this->activoDe($resultado, 2));
        $this->assertFalse($this->activoDe($resultado, 3));
    }

    public function test_apagar_un_hijo_mantiene_el_padre_si_queda_otro(): void
    {
        $inicio = $this->catalogo(true, true, true);
        $resultado = FuncionalidadJerarquia::aplicar($inicio, 2, false);

        $this->assertTrue($this->activoDe($resultado, 1));
        $this->assertFalse($this->activoDe($resultado, 2));
        $this->assertTrue($this->activoDe($resultado, 3));
    }

    public function test_apagar_el_ultimo_hijo_apaga_el_padre(): void
    {
        $inicio = $this->catalogo(true, true, false);
        $resultado = FuncionalidadJerarquia::aplicar($inicio, 2, false);

        $this->assertFalse($this->activoDe($resultado, 1));
        $this->assertFalse($this->activoDe($resultado, 2));
    }

    public function test_funcionalidad_plana_no_arrastra_a_clinica(): void
    {
        $resultado = FuncionalidadJerarquia::aplicar($this->catalogo(true, true, false), 4, false);

        $this->assertTrue($this->activoDe($resultado, 1));
        $this->assertTrue($this->activoDe($resultado, 2));
        $this->assertFalse($this->activoDe($resultado, 4));
    }

    public function test_lote_al_prender_padre_deja_hijos_apagados(): void
    {
        $resultado = FuncionalidadJerarquia::aplicarCambios($this->catalogo(), [
            ['id' => 1, 'activo' => true],
        ]);

        $this->assertTrue($this->activoDe($resultado, 1));
        $this->assertFalse($this->activoDe($resultado, 2));
    }

    public function test_lote_al_apagar_ultimo_hijo_apaga_padre_aunque_el_cambio_no_lo_incluya(): void
    {
        $resultado = FuncionalidadJerarquia::aplicarCambios($this->catalogo(true, true, false), [
            ['id' => 2, 'activo' => false],
        ]);

        $this->assertFalse($this->activoDe($resultado, 1));
        $this->assertFalse($this->activoDe($resultado, 2));
    }

    public function test_seeder_registra_clinica_y_pacientes(): void
    {
        $src = file_get_contents(__DIR__.'/../../../../database/seeders/ClinicaFuncionalidadSeeder.php');

        $this->assertStringContainsString("'slug' => 'clinica'", $src);
        $this->assertStringContainsString("'slug' => 'clinica-pacientes'", $src);
        $this->assertStringContainsString("'parent_id' => \$clinica->id", $src);
    }

    /**
     * @return list<array{id:int, parent_id:?int, activo:bool}>
     */
    private function catalogo(bool $clinica = false, bool $pacientes = false, bool $consultas = false): array
    {
        return [
            ['id' => 1, 'parent_id' => null, 'activo' => $clinica],
            ['id' => 2, 'parent_id' => 1, 'activo' => $pacientes],
            ['id' => 3, 'parent_id' => 1, 'activo' => $consultas],
            ['id' => 4, 'parent_id' => null, 'activo' => true],
        ];
    }

    /**
     * @param  list<array{id:int, parent_id:?int, activo:bool}>  $items
     */
    private function activoDe(array $items, int $id): bool
    {
        foreach ($items as $item) {
            if ($item['id'] === $id) {
                return $item['activo'];
            }
        }

        $this->fail("No está la funcionalidad {$id}");
    }
}
