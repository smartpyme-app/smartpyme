<?php

namespace Tests\Unit\Support\Restaurante;

use App\Support\Restaurante\MesasImportPlanner;
use PHPUnit\Framework\TestCase;

final class MesasImportPlannerTest extends TestCase
{
    public function test_plan_crea_omite_y_error_zona(): void
    {
        $zonas = [
            (object) ['id' => 10, 'nombre' => 'Deck 1'],
            (object) ['id' => 11, 'nombre' => ' Terraza '],
        ];
        $indexed = MesasImportPlanner::indexZonas($zonas);
        $this->assertSame([], $indexed['errors']);
        $this->assertArrayHasKey('Deck 1', $indexed['map']);
        $this->assertArrayHasKey('Terraza', $indexed['map']);

        $rows = [
            ['fila' => 2, 'numero' => '1', 'capacidad' => 4, 'zona_nombre' => 'Deck 1', 'orden' => 1], // ya existe
            ['fila' => 3, 'numero' => '2', 'capacidad' => null, 'zona_nombre' => 'Deck 1', 'orden' => null], // crear
            ['fila' => 4, 'numero' => '2', 'capacidad' => 4, 'zona_nombre' => 'Deck 1', 'orden' => 2], // dup en Excel
            ['fila' => 5, 'numero' => '9', 'capacidad' => 4, 'zona_nombre' => 'No Existe', 'orden' => 1],
        ];
        $existing = ['10|1' => true];

        $plan = MesasImportPlanner::plan($rows, $indexed['map'], $existing);

        $this->assertCount(1, $plan['crear']);
        $this->assertSame('2', $plan['crear'][0]['numero']);
        $this->assertSame(4, $plan['crear'][0]['capacidad']);
        $this->assertSame(0, $plan['crear'][0]['orden']);
        $this->assertCount(2, $plan['omitir']);
        $this->assertCount(1, $plan['errores']);
        $this->assertStringContainsString('zona', strtolower($plan['errores'][0]['motivo']));
    }

    public function test_zona_ambigua(): void
    {
        $indexed = MesasImportPlanner::indexZonas([
            (object) ['id' => 1, 'nombre' => 'Salon'],
            (object) ['id' => 2, 'nombre' => 'Salon'],
        ]);
        $this->assertNotEmpty($indexed['errors']);
        $this->assertSame([], $indexed['map']);
    }
}
