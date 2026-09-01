<?php

namespace Tests\Unit\Services\MH;

use App\Services\MH\MHPruebasMasivasOutcome;
use PHPUnit\Framework\TestCase;

class MHPruebasMasivasOutcomeTest extends TestCase
{
    public function test_giro_coincide_con_catalogo_tras_normalizar_espacios(): void
    {
        $this->assertTrue(MHPruebasMasivasOutcome::giroCoincideConCatalogo(
            '  Venta al por menor  ',
            'Venta al por menor'
        ));
    }

    public function test_giro_no_coincide_si_el_texto_difiere(): void
    {
        $this->assertFalse(MHPruebasMasivasOutcome::giroCoincideConCatalogo(
            'Comercio varios',
            'Venta al por menor'
        ));
    }

    public function test_giro_vacio_o_sin_catalogo_no_coincide(): void
    {
        $this->assertFalse(MHPruebasMasivasOutcome::giroCoincideConCatalogo(null, 'Venta al por menor'));
        $this->assertFalse(MHPruebasMasivasOutcome::giroCoincideConCatalogo('Venta al por menor', null));
        $this->assertFalse(MHPruebasMasivasOutcome::giroCoincideConCatalogo('', 'Venta al por menor'));
    }

    public function test_fallo_total_cuando_no_hay_exitosos(): void
    {
        $this->assertTrue(MHPruebasMasivasOutcome::esFalloTotal(0, 90));
        $this->assertTrue(MHPruebasMasivasOutcome::esFalloTotal(0, 0));
        $this->assertFalse(MHPruebasMasivasOutcome::esFalloTotal(1, 89));
    }

    public function test_exito_completo_solo_si_hay_emisiones_y_cero_fallidos(): void
    {
        $this->assertTrue(MHPruebasMasivasOutcome::esExitoCompleto(90, 0));
        $this->assertFalse(MHPruebasMasivasOutcome::esExitoCompleto(80, 10));
        $this->assertFalse(MHPruebasMasivasOutcome::esExitoCompleto(0, 0));
    }

    public function test_detecta_rechazo_de_emisor_por_desc_actividad(): void
    {
        $mensaje = 'Error al procesar DTE: {"estado":"RECHAZADO","codigoMsg":"096","observaciones":["Campo #\\/emisor\\/descActividad contiene un valor inválido"]}';

        $this->assertTrue(MHPruebasMasivasOutcome::esRechazoEmisorIrrecuperable($mensaje));
        $this->assertFalse(MHPruebasMasivasOutcome::esRechazoEmisorIrrecuperable('Error de conexión: timeout'));
    }

    public function test_asunto_de_correo_no_dice_completadas_si_hubo_fallos(): void
    {
        $this->assertStringStartsWith(
            'Error en Pruebas Masivas MH',
            MHPruebasMasivasOutcome::asuntoCorreo('Facturas Consumidor Final', 0, 90)
        );
        $this->assertStringStartsWith(
            'Pruebas Masivas MH finalizadas con errores',
            MHPruebasMasivasOutcome::asuntoCorreo('Facturas Consumidor Final', 10, 5)
        );
        $this->assertStringStartsWith(
            'Pruebas Masivas MH Completadas',
            MHPruebasMasivasOutcome::asuntoCorreo('Facturas Consumidor Final', 90, 0)
        );
    }

    public function test_resumen_de_fallo_incluye_detalle_de_hacienda_y_giro(): void
    {
        $resumen = MHPruebasMasivasOutcome::resumenFallo([
            'exitosos' => 0,
            'fallidos' => 1,
            'detenido_por_emisor' => true,
            'detalles' => [[
                'status' => 'Error',
                'message' => 'Campo #/emisor/descActividad contiene un valor inválido',
            ]],
        ], 90);

        $this->assertStringContainsString('Hacienda no aceptó las pruebas', $resumen);
        $this->assertStringContainsString('descActividad', $resumen);
        $this->assertStringContainsString('giro', $resumen);
        $this->assertStringContainsString('Se detuvo el resto del lote', $resumen);
    }
}
