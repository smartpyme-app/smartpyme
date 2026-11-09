<?php

namespace Tests\Unit\Models\MH;

use App\Models\MH\Distrito;
use Tests\TestCase;

class DistritoMunicipioRelationTest extends TestCase
{
  /** @didactic-demo: relación municipio no debe usar foreignKey array (rompía GET /api/distritos con eager load). */
  public function test_municipio_relation_uses_scalar_foreign_key(): void
    {
        $distrito = new Distrito();
        $relation = $distrito->municipio();

        $this->assertSame('cod_municipio', $relation->getForeignKeyName());
        $this->assertIsString($relation->getForeignKeyName());
    }
}
