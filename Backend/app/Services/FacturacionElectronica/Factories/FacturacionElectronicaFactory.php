<?php

namespace App\Services\FacturacionElectronica\Factories;

use App\Services\FacturacionElectronica\Contracts\FacturacionElectronicaInterface;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Log;

/**
 * Factory para crear instancias de facturación electrónica según el país
 * 
 * Este factory utiliza el patrón Factory para crear las implementaciones
 * correctas de facturación electrónica basándose en el país de la empresa
 * y el tipo de documento.
 * 
 * @package App\Services\FacturacionElectronica\Factories
 */
class FacturacionElectronicaFactory
{
    /**
     * Crea una instancia de facturación electrónica según el país y tipo de documento
     * 
     * @param string|Empresa $pais Código de país (SV, CR) o instancia de Empresa
     * @param string $tipoDocumento Tipo de documento (01=Factura, 03=CCF, 05=NotaCrédito, etc.)
     * @return FacturacionElectronicaInterface
     * @throws \Exception Si el país no está soportado o el tipo de documento no es válido
     */
    public static function crear($pais, string $tipoDocumento): FacturacionElectronicaInterface
    {
        // Obtener código de país
        $codPais = self::obtenerCodigoPais($pais);
        
        if (!$codPais) {
            throw new \Exception("No se pudo determinar el país para facturación electrónica");
        }

        // Crear instancia según el país
        switch (strtoupper($codPais)) {
            case 'SV':
                return self::crearElSalvador($tipoDocumento);
            
            case 'CR':
                return self::crearCostaRica($tipoDocumento);
            
            default:
                Log::error("País no soportado para facturación electrónica", [
                    'cod_pais' => $codPais,
                    'tipo_documento' => $tipoDocumento
                ]);
                throw new \Exception("País no soportado para facturación electrónica: {$codPais}");
        }
    }

    /**
     * Crea una instancia para El Salvador
     * 
     * @param string $tipoDocumento Tipo de documento
     * @return FacturacionElectronicaInterface
     * @throws \Exception Si el tipo de documento no es válido
     */
    private static function crearElSalvador(string $tipoDocumento): FacturacionElectronicaInterface
    {
        // Mapeo de tipos de documento a clases
        $clases = [
            '01' => \App\Services\FacturacionElectronica\Implementations\ElSalvador\ElSalvadorFactura::class,
            '03' => \App\Services\FacturacionElectronica\Implementations\ElSalvador\ElSalvadorCCF::class,
            '05' => \App\Services\FacturacionElectronica\Implementations\ElSalvador\ElSalvadorNotaCredito::class,
            '06' => \App\Services\FacturacionElectronica\Implementations\ElSalvador\ElSalvadorNotaDebito::class,
            '11' => \App\Services\FacturacionElectronica\Implementations\ElSalvador\ElSalvadorFacturaExportacion::class,
        ];

        if (!isset($clases[$tipoDocumento])) {
            throw new \Exception("Tipo de documento no soportado para El Salvador: {$tipoDocumento}");
        }

        $clase = $clases[$tipoDocumento];
        
        if (!class_exists($clase)) {
            throw new \Exception("Clase de implementación no encontrada: {$clase}");
        }

        return new $clase();
    }

    /**
     * Crea una instancia para Costa Rica
     * 
     * @param string $tipoDocumento Tipo de documento
     * @return FacturacionElectronicaInterface
     * @throws \Exception Si el tipo de documento no es válido o no está implementado
     */
    private static function crearCostaRica(string $tipoDocumento): FacturacionElectronicaInterface
    {
        // Mapeo de tipos de documento a clases (por implementar)
        $clases = [
            '01' => \App\Services\FacturacionElectronica\Implementations\CostaRica\CostaRicaFactura::class,
            '05' => \App\Services\FacturacionElectronica\Implementations\CostaRica\CostaRicaNotaCredito::class,
            '06' => \App\Services\FacturacionElectronica\Implementations\CostaRica\CostaRicaNotaDebito::class,
        ];

        if (!isset($clases[$tipoDocumento])) {
            throw new \Exception("Tipo de documento no soportado para Costa Rica: {$tipoDocumento}");
        }

        $clase = $clases[$tipoDocumento];
        
        if (!class_exists($clase)) {
            throw new \Exception("Clase de implementación no encontrada para Costa Rica: {$clase}. Aún no está implementada.");
        }

        return new $clase();
    }

    /**
     * Obtiene el código de país desde una instancia de Empresa o string
     * 
     * @param string|Empresa $pais
     * @return string|null Código de país (SV, CR, etc.)
     */
    private static function obtenerCodigoPais($pais): ?string
    {
        if ($pais instanceof Empresa) {
            // Priorizar fe_pais, luego cod_pais
            return $pais->fe_pais ?? $pais->cod_pais;
        }

        if (is_string($pais)) {
            return strtoupper($pais);
        }

        return null;
    }

    /**
     * Obtiene el tipo de documento desde el nombre del documento
     * 
     * @param string $nombreDocumento Nombre del documento (Factura, Crédito fiscal, etc.)
     * @return string|null Código del tipo de documento
     */
    public static function obtenerTipoDocumento(string $nombreDocumento): ?string
    {
        $mapeo = [
            'Factura' => '01',
            'Crédito fiscal' => '03',
            'Comprobante de crédito fiscal' => '03',
            'Nota de crédito' => '05',
            'Nota de débito' => '06',
            'Factura de exportación' => '11',
            'Sujeto excluido' => '14',
        ];

        return $mapeo[$nombreDocumento] ?? null;
    }
}
