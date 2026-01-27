<?php

namespace App\Services\FacturacionElectronica;

use App\Services\FacturacionElectronica\Factories\FacturacionElectronicaFactory;
use App\Services\FacturacionElectronica\Contracts\FacturacionElectronicaInterface;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Log;

/**
 * Servicio principal de facturación electrónica
 * 
 * Este servicio actúa como punto de entrada principal para todas las operaciones
 * de facturación electrónica, delegando a las implementaciones específicas por país.
 * 
 * @package App\Services\FacturacionElectronica
 */
class FacturacionElectronicaService
{
    /**
     * Genera un DTE (Documento Tributario Electrónico)
     * 
     * @param mixed $documento Venta, Compra, Devolucion, Gasto, etc.
     * @return array Estructura del DTE
     * @throws \Exception Si hay errores en la generación
     */
    public function generarDTE($documento): array
    {
        try {
            // Obtener empresa del documento
            $empresa = $this->obtenerEmpresa($documento);
            
            // Validar que la empresa tenga FE habilitada
            $this->validarFacturacionElectronica($empresa);
            
            // Obtener tipo de documento
            $tipoDocumento = $this->obtenerTipoDocumento($documento);
            
            if (!$tipoDocumento) {
                throw new \Exception("No se pudo determinar el tipo de documento");
            }
            
            // Crear instancia según país
            $fe = FacturacionElectronicaFactory::crear($empresa, $tipoDocumento);
            
            // Generar DTE
            Log::info("Generando DTE", [
                'pais' => $empresa->fe_pais ?? $empresa->cod_pais,
                'tipo_documento' => $tipoDocumento,
                'documento_id' => $documento->id ?? null
            ]);
            
            return $fe->generarDTE($documento);
            
        } catch (\Exception $e) {
            Log::error("Error al generar DTE", [
                'error' => $e->getMessage(),
                'documento_id' => $documento->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Firma un DTE
     * 
     * @param array $dte Documento a firmar
     * @param mixed $documento Documento original
     * @return array Documento firmado
     * @throws \Exception Si hay errores en la firma
     */
    public function firmarDTE(array $dte, $documento): array
    {
        try {
            $empresa = $this->obtenerEmpresa($documento);
            $tipoDocumento = $dte['identificacion']['tipoDte'] ?? $this->obtenerTipoDocumento($documento);
            
            $fe = FacturacionElectronicaFactory::crear($empresa, $tipoDocumento);
            
            return $fe->firmarDTE($dte);
            
        } catch (\Exception $e) {
            Log::error("Error al firmar DTE", [
                'error' => $e->getMessage(),
                'documento_id' => $documento->id ?? null
            ]);
            throw $e;
        }
    }

    /**
     * Envía un DTE a la autoridad tributaria
     * 
     * @param array $dteFirmado Documento firmado
     * @param mixed $documento Documento original
     * @return array Respuesta de la autoridad tributaria
     * @throws \Exception Si hay errores en el envío
     */
    public function enviarDTE(array $dteFirmado, $documento): array
    {
        try {
            $empresa = $this->obtenerEmpresa($documento);
            $tipoDocumento = $dteFirmado['identificacion']['tipoDte'] ?? $this->obtenerTipoDocumento($documento);
            
            $fe = FacturacionElectronicaFactory::crear($empresa, $tipoDocumento);
            
            Log::info("Enviando DTE a autoridad tributaria", [
                'pais' => $empresa->fe_pais ?? $empresa->cod_pais,
                'tipo_documento' => $tipoDocumento,
                'documento_id' => $documento->id ?? null
            ]);
            
            return $fe->enviarDTE($dteFirmado, $documento);
            
        } catch (\Exception $e) {
            Log::error("Error al enviar DTE", [
                'error' => $e->getMessage(),
                'documento_id' => $documento->id ?? null
            ]);
            throw $e;
        }
    }

    /**
     * Anula un DTE
     * 
     * @param array $dte Documento a anular
     * @param mixed $documento Documento original
     * @return array Documento de anulación
     * @throws \Exception Si hay errores en la anulación
     */
    public function anularDTE(array $dte, $documento): array
    {
        try {
            $empresa = $this->obtenerEmpresa($documento);
            $tipoDocumento = $dte['identificacion']['tipoDte'] ?? $this->obtenerTipoDocumento($documento);
            
            $fe = FacturacionElectronicaFactory::crear($empresa, $tipoDocumento);
            
            return $fe->anularDTE($dte, $documento);
            
        } catch (\Exception $e) {
            Log::error("Error al anular DTE", [
                'error' => $e->getMessage(),
                'documento_id' => $documento->id ?? null
            ]);
            throw $e;
        }
    }

    /**
     * Consulta el estado de un DTE
     * 
     * @param string $codigoGeneracion Código de generación
     * @param string $tipoDte Tipo de documento
     * @param mixed $documento Documento original (para obtener país)
     * @return array Estado del documento
     * @throws \Exception Si hay errores en la consulta
     */
    public function consultarDTE(string $codigoGeneracion, string $tipoDte, $documento): array
    {
        try {
            $empresa = $this->obtenerEmpresa($documento);
            $fe = FacturacionElectronicaFactory::crear($empresa, $tipoDte);
            
            $config = $fe->obtenerConfiguracion();
            $ambiente = $empresa->fe_ambiente ?? '00';
            
            return $fe->consultarDTE($codigoGeneracion, $tipoDte, $ambiente);
            
        } catch (\Exception $e) {
            Log::error("Error al consultar DTE", [
                'error' => $e->getMessage(),
                'codigo_generacion' => $codigoGeneracion
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene la empresa desde el documento
     * 
     * @param mixed $documento
     * @return Empresa
     * @throws \Exception Si no se puede obtener la empresa
     */
    private function obtenerEmpresa($documento): Empresa
    {
        if (method_exists($documento, 'empresa')) {
            $empresa = $documento->empresa()->first();
            if ($empresa) {
                return $empresa;
            }
        }
        
        if (isset($documento->id_empresa)) {
            $empresa = Empresa::find($documento->id_empresa);
            if ($empresa) {
                return $empresa;
            }
        }
        
        throw new \Exception("No se pudo obtener la empresa del documento");
    }

    /**
     * Valida que la empresa tenga facturación electrónica habilitada
     * 
     * @param Empresa $empresa
     * @throws \Exception Si no está habilitada
     */
    private function validarFacturacionElectronica(Empresa $empresa): void
    {
        if (!$empresa->facturacion_electronica) {
            throw new \Exception("La facturación electrónica no está habilitada para esta empresa");
        }
        
        $codPais = $empresa->fe_pais ?? $empresa->cod_pais;
        if (!$codPais) {
            throw new \Exception("No se ha configurado el país para facturación electrónica");
        }
    }

    /**
     * Obtiene el tipo de documento desde el documento
     * 
     * @param mixed $documento
     * @return string|null Código del tipo de documento
     */
    private function obtenerTipoDocumento($documento): ?string
    {
        // Si tiene tipo_dte directamente
        if (isset($documento->tipo_dte)) {
            return $documento->tipo_dte;
        }
        
        // Si tiene nombre_documento, mapear
        if (isset($documento->nombre_documento)) {
            return FacturacionElectronicaFactory::obtenerTipoDocumento($documento->nombre_documento);
        }
        
        return null;
    }
}
