<?php

namespace Tests\Unit\Services\FacturacionElectronica;

use Tests\TestCase;
use App\Services\FacturacionElectronica\Factories\FacturacionElectronicaFactory;
use App\Services\FacturacionElectronica\Contracts\FacturacionElectronicaInterface;
use App\Models\Admin\Empresa;

class FacturacionElectronicaFactoryTest extends TestCase
{

    /**
     * Test que la factory puede crear instancia para El Salvador
     */
    public function test_crear_instancia_el_salvador()
    {
        $empresa = new Empresa();
        $empresa->fe_pais = 'SV';
        $empresa->cod_pais = 'SV';

        $factura = FacturacionElectronicaFactory::crear($empresa, '01');
        
        $this->assertInstanceOf(FacturacionElectronicaInterface::class, $factura);
        $this->assertEquals('El Salvador', $factura->obtenerConfiguracion()['nombre'] ?? null);
    }

    /**
     * Test que la factory lanza excepción para Costa Rica (aún no implementado)
     */
    public function test_crear_instancia_costa_rica_no_implementado()
    {
        $empresa = new Empresa();
        $empresa->fe_pais = 'CR';
        $empresa->cod_pais = 'CR';

        // Por ahora CR no está implementado, debería lanzar excepción al intentar crear la clase
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Clase de implementación no encontrada');
        
        FacturacionElectronicaFactory::crear($empresa, '01');
    }

    /**
     * Test que la factory lanza excepción para país no soportado
     */
    public function test_crear_instancia_pais_no_soportado()
    {
        $empresa = new Empresa();
        $empresa->fe_pais = 'GT';
        $empresa->cod_pais = 'GT';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('País no soportado');
        
        FacturacionElectronicaFactory::crear($empresa, '01');
    }

    /**
     * Test que la factory puede crear diferentes tipos de documento para El Salvador
     */
    public function test_crear_diferentes_tipos_documento_el_salvador()
    {
        $empresa = new Empresa();
        $empresa->fe_pais = 'SV';
        $empresa->cod_pais = 'SV';

        $tipos = [
            '01' => 'Factura',
            '03' => 'CCF',
            '05' => 'Nota de Crédito',
            '06' => 'Nota de Débito',
            '11' => 'Factura de Exportación',
        ];

        foreach ($tipos as $codigo => $nombre) {
            $dte = FacturacionElectronicaFactory::crear($empresa, $codigo);
            $this->assertInstanceOf(FacturacionElectronicaInterface::class, $dte);
        }
    }

    /**
     * Test que la factory lanza excepción para tipo de documento no soportado
     */
    public function test_crear_tipo_documento_no_soportado()
    {
        $empresa = new Empresa();
        $empresa->fe_pais = 'SV';
        $empresa->cod_pais = 'SV';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tipo de documento no soportado');
        
        FacturacionElectronicaFactory::crear($empresa, '99');
    }

    /**
     * Test que obtenerTipoDocumento funciona correctamente
     */
    public function test_obtener_tipo_documento()
    {
        $this->assertEquals('01', FacturacionElectronicaFactory::obtenerTipoDocumento('Factura'));
        $this->assertEquals('03', FacturacionElectronicaFactory::obtenerTipoDocumento('Crédito fiscal'));
        $this->assertEquals('05', FacturacionElectronicaFactory::obtenerTipoDocumento('Nota de crédito'));
        $this->assertEquals('06', FacturacionElectronicaFactory::obtenerTipoDocumento('Nota de débito'));
        $this->assertEquals('11', FacturacionElectronicaFactory::obtenerTipoDocumento('Factura de exportación'));
        $this->assertNull(FacturacionElectronicaFactory::obtenerTipoDocumento('Documento inexistente'));
    }

    /**
     * Test que la factory acepta empresa con fe_pais o cod_pais
     */
    public function test_acepta_empresa_con_fe_pais_o_cod_pais()
    {
        // Test con fe_pais
        $empresa1 = new Empresa();
        $empresa1->fe_pais = 'SV';
        
        $factura1 = FacturacionElectronicaFactory::crear($empresa1, '01');
        $this->assertInstanceOf(FacturacionElectronicaInterface::class, $factura1);

        // Test con cod_pais (fallback)
        $empresa2 = new Empresa();
        $empresa2->cod_pais = 'SV';
        
        $factura2 = FacturacionElectronicaFactory::crear($empresa2, '01');
        $this->assertInstanceOf(FacturacionElectronicaInterface::class, $factura2);
    }
}
