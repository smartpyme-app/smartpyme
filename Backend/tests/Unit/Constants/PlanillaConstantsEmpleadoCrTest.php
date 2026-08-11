<?php

namespace Tests\Unit\Constants;

use App\Constants\PlanillaConstants;
use PHPUnit\Framework\TestCase;

class PlanillaConstantsEmpleadoCrTest extends TestCase
{
    public function test_id_types_cr_tiene_cedula_y_dimex(): void
    {
        $tipos = PlanillaConstants::getIdTypesCr();
        $this->assertSame('Cédula', $tipos[PlanillaConstants::ID_TYPE_CEDULA]);
        $this->assertSame('DIMEX', $tipos[PlanillaConstants::ID_TYPE_DIMEX]);
        $this->assertSame([1, 2], PlanillaConstants::idTypesCrValidos());
    }

    public function test_tipos_salario_y_categorias_cns(): void
    {
        $this->assertSame([1, 2, 3], PlanillaConstants::tiposSalarioValidos());
        $cats = PlanillaConstants::getCategoriasOcupacionalesCr();
        $this->assertArrayHasKey('no_calificada', $cats);
        $this->assertSame(array_keys($cats), PlanillaConstants::categoriasOcupacionalesCrKeys());
    }
}
