<?php

namespace Tests\Unit\Services\Clinica;

use App\Services\Clinica\EdadPaciente;
use App\Services\Clinica\ExpedienteNumero;
use App\Services\Clinica\PacienteReglas;
use App\Services\Clinica\ResponsableReglas;
use PHPUnit\Framework\TestCase;

class PacienteReglasTest extends TestCase
{
    public function test_edad_se_calcula_y_no_se_inventa_sin_fecha(): void
    {
        $hoy = new \DateTimeImmutable('2026-09-30');

        $this->assertSame('desconocida', EdadPaciente::texto(null, $hoy));
        $this->assertSame('desconocida', EdadPaciente::texto('2026-10-01', $hoy));
        $this->assertSame('2 años', EdadPaciente::texto('2024-09-30', $hoy));
        $this->assertSame('1 año', EdadPaciente::texto('2025-09-30', $hoy));
        $this->assertSame('3 meses', EdadPaciente::texto('2026-06-30', $hoy));
        $this->assertSame('10 días', EdadPaciente::texto('2026-09-20', $hoy));
        $this->assertSame(2, EdadPaciente::anios('2024-09-30', $hoy));
        $this->assertNull(EdadPaciente::anios(null, $hoy));
    }

    public function test_expediente_avanza_sin_reutilizar_el_ultimo(): void
    {
        $this->assertSame(1, ExpedienteNumero::siguiente(0));
        $this->assertSame(8, ExpedienteNumero::siguiente(7));
    }

    public function test_raza_es_obligatoria_solo_si_la_especie_tiene_razas(): void
    {
        $this->assertFalse(PacienteReglas::razaObligatoria(0));
        $this->assertTrue(PacienteReglas::razaObligatoria(2));
    }

    public function test_documento_vacio_no_compite_en_unicidad(): void
    {
        $this->assertNull(PacienteReglas::vacio('   '));
        $this->assertSame('001-2', PacienteReglas::vacio(' 001-2 '));
    }

    public function test_el_alta_de_un_animal_exige_un_solo_principal(): void
    {
        $sinPrincipal = ResponsableReglas::puedeCerrarAlta('ANIMAL', null, 18, []);
        $this->assertNotNull($sinPrincipal);

        $conPrincipal = ResponsableReglas::puedeCerrarAlta('ANIMAL', null, 18, [
            ['es_principal' => true, 'es_el_paciente' => false],
        ]);
        $this->assertNull($conPrincipal);

        $dos = ResponsableReglas::puedeCerrarAlta('ANIMAL', null, 18, [
            ['es_principal' => true, 'es_el_paciente' => false],
            ['es_principal' => true, 'es_el_paciente' => false],
        ]);
        $this->assertNotNull($dos);
    }

    public function test_un_menor_no_cierra_el_alta_si_solo_esta_el_mismo(): void
    {
        $menor = ResponsableReglas::puedeCerrarAlta('HUMANO', 10, 18, [
            ['es_principal' => true, 'es_el_paciente' => true],
        ]);
        $this->assertNotNull($menor);

        $conTutor = ResponsableReglas::puedeCerrarAlta('HUMANO', 10, 18, [
            ['es_principal' => true, 'es_el_paciente' => false],
        ]);
        $this->assertNull($conTutor);

        $adulto = ResponsableReglas::puedeCerrarAlta('HUMANO', 30, 18, [
            ['es_principal' => true, 'es_el_paciente' => true],
        ]);
        $this->assertNull($adulto);

        $limiteEmpresa = ResponsableReglas::puedeCerrarAlta('HUMANO', 20, 21, [
            ['es_principal' => true, 'es_el_paciente' => true],
        ]);
        $this->assertNotNull($limiteEmpresa);
    }
}
