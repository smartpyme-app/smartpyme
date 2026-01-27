<?php

namespace App\Services\FacturacionElectronica\Implementations\ElSalvador;

use App\Services\FacturacionElectronica\Contracts\FacturacionElectronicaInterface;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Clase base para implementación de facturación electrónica de El Salvador
 * 
 * Contiene la lógica común para autenticación, firma, envío y anulación
 * de documentos electrónicos según las especificaciones de MH El Salvador.
 * 
 * @package App\Services\FacturacionElectronica\Implementations\ElSalvador
 */
abstract class ElSalvadorFE implements FacturacionElectronicaInterface
{
    protected $empresa;
    protected $config;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = config('facturacion_electronica.paises.SV');
    }

    /**
     * Obtiene la configuración de El Salvador
     * 
     * @return array
     */
    public function obtenerConfiguracion(): array
    {
        return $this->config;
    }

    /**
     * Autentica con la API de MH El Salvador
     * 
     * @param Empresa $empresa
     * @return array Respuesta de autenticación con token
     * @throws \Exception Si hay errores en la autenticación
     */
    protected function auth(Empresa $empresa): array
    {
        $this->empresa = $empresa;
        
        $ambiente = $empresa->fe_ambiente ?? '00';
        $urlAuth = $this->config['urls'][$ambiente == '01' ? 'produccion' : 'prueba']['auth'];
        
        $usuario = $empresa->fe_usuario ?? $empresa->mh_usuario;
        $contrasena = $empresa->fe_contrasena ?? $empresa->mh_contrasena;
        
        if (!$usuario || !$contrasena) {
            throw new \Exception("Usuario y contraseña de MH no configurados");
        }

        try {
            $response = Http::asForm()->post($urlAuth, [
                'user' => str_replace('-', '', $usuario),
                'pwd' => $contrasena
            ]);

            $data = $response->json();
            
            if ($response->failed() || (isset($data['status']) && $data['status'] == 'ERROR')) {
                Log::error("Error en autenticación MH", [
                    'response' => $data,
                    'empresa_id' => $empresa->id
                ]);
                return [
                    'status' => 'ERROR',
                    'body' => $data['body'] ?? ['descripcionMsg' => 'Error de autenticación']
                ];
            }

            return $data;
            
        } catch (\Exception $e) {
            Log::error("Excepción en autenticación MH", [
                'error' => $e->getMessage(),
                'empresa_id' => $empresa->id
            ]);
            throw new \Exception("Error al autenticar con MH: " . $e->getMessage());
        }
    }

    /**
     * Firma electrónicamente un DTE
     * 
     * @param array $dte Documento a firmar
     * @return array Documento firmado
     * @throws \Exception Si hay errores en la firma
     */
    public function firmarDTE(array $dte): array
    {
        if (!$this->empresa) {
            throw new \Exception("Empresa no configurada para firmar DTE");
        }

        $urlFirmador = $this->config['firmador']['url'] ?? $this->config['firmador']['alternativa'];
        
        $passwordCertificado = $this->empresa->fe_certificado_password ?? $this->empresa->mh_pwd_certificado;
        
        if (!$passwordCertificado) {
            throw new \Exception("Contraseña del certificado no configurada");
        }

        try {
            $datosFirma = [
                'nit' => str_replace('-', '', $this->empresa->nit),
                'activo' => true,
                'passwordPri' => $passwordCertificado,
                'dteJson' => $dte
            ];

            $response = Http::timeout(30)->post($urlFirmador, $datosFirma);
            $data = $response->json();

            if ($response->failed() || (isset($data['status']) && $data['status'] == 'ERROR')) {
                Log::error("Error al firmar DTE", [
                    'response' => $data,
                    'empresa_id' => $this->empresa->id
                ]);
                return [
                    'status' => 'ERROR',
                    'body' => $data['body'] ?? ['mensaje' => 'Error al firmar documento']
                ];
            }

            return $data;
            
        } catch (\Exception $e) {
            Log::error("Excepción al firmar DTE", [
                'error' => $e->getMessage(),
                'empresa_id' => $this->empresa->id ?? null
            ]);
            throw new \Exception("Error al firmar DTE: " . $e->getMessage());
        }
    }

    /**
     * Envía un DTE firmado a MH El Salvador
     * 
     * @param array $dteFirmado Documento firmado
     * @param mixed $documento Documento original
     * @return array Respuesta de MH
     * @throws \Exception Si hay errores en el envío
     */
    public function enviarDTE(array $dteFirmado, $documento): array
    {
        if (!$this->empresa) {
            $this->empresa = $documento->empresa()->first();
        }

        // Autenticar primero
        $auth = $this->auth($this->empresa);
        
        if (isset($auth['status']) && $auth['status'] == 'ERROR') {
            throw new \Exception($auth['body']['descripcionMsg'] ?? 'Error de autenticación');
        }

        $token = $auth['body']['token'] ?? null;
        if (!$token) {
            throw new \Exception("No se obtuvo token de autenticación");
        }

        $ambiente = $this->empresa->fe_ambiente ?? '00';
        $urlRecepcion = $this->config['urls'][$ambiente == '01' ? 'produccion' : 'prueba']['recepcion'];
        
        $tipoDte = $dteFirmado['identificacion']['tipoDte'] ?? $documento->tipo_dte ?? '01';
        $version = $dteFirmado['identificacion']['version'] ?? 1;
        $codigoGeneracion = $dteFirmado['identificacion']['codigoGeneracion'] ?? $documento->codigo_generacion;

        try {
            $datosEnvio = [
                'ambiente' => $ambiente,
                'idEnvio' => $documento->id ?? uniqid(),
                'version' => $version,
                'tipoDte' => $tipoDte,
                'documento' => $dteFirmado['body'] ?? $dteFirmado,
                'codigoGeneracion' => $codigoGeneracion
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'SmartPyme',
                'Authorization' => $token
            ])->timeout(30)->post($urlRecepcion, $datosEnvio);

            $data = $response->json();

            if ($response->failed()) {
                Log::error("Error al enviar DTE a MH", [
                    'response' => $data,
                    'documento_id' => $documento->id ?? null
                ]);
                throw new \Exception("Error al enviar DTE: " . ($data['descripcionMsg'] ?? 'Error desconocido'));
            }

            return $data;
            
        } catch (\Exception $e) {
            Log::error("Excepción al enviar DTE", [
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
        if (!$this->empresa) {
            $this->empresa = $documento->empresa()->first();
        }

        // Autenticar primero
        $auth = $this->auth($this->empresa);
        
        if (isset($auth['status']) && $auth['status'] == 'ERROR') {
            throw new \Exception($auth['body']['descripcionMsg'] ?? 'Error de autenticación');
        }

        $token = $auth['body']['token'] ?? null;
        if (!$token) {
            throw new \Exception("No se obtuvo token de autenticación");
        }

        // Generar documento de anulación (debe ser implementado por clases hijas)
        $dteAnular = $this->generarDTEAnulado($dte, $documento);
        
        // Firmar documento de anulación
        $dteAnularFirmado = $this->firmarDTE($dteAnular);
        
        if (isset($dteAnularFirmado['status']) && $dteAnularFirmado['status'] == 'ERROR') {
            throw new \Exception($dteAnularFirmado['body']['mensaje'] ?? 'Error al firmar documento de anulación');
        }

        $ambiente = $this->empresa->fe_ambiente ?? '00';
        $urlAnulacion = $this->config['urls'][$ambiente == '01' ? 'produccion' : 'prueba']['anulacion'];
        
        $version = $dteAnular['identificacion']['version'] ?? 2;

        try {
            $datosAnulacion = [
                'ambiente' => $ambiente,
                'idEnvio' => $documento->id ?? uniqid(),
                'version' => $version,
                'documento' => $dteAnularFirmado['body'] ?? $dteAnularFirmado
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'SmartPyme',
                'Authorization' => $token
            ])->timeout(30)->post($urlAnulacion, $datosAnulacion);

            $data = $response->json();

            if ($response->failed()) {
                Log::error("Error al anular DTE en MH", [
                    'response' => $data,
                    'documento_id' => $documento->id ?? null
                ]);
                throw new \Exception("Error al anular DTE: " . ($data['descripcionMsg'] ?? 'Error desconocido'));
            }

            return $data;
            
        } catch (\Exception $e) {
            Log::error("Excepción al anular DTE", [
                'error' => $e->getMessage(),
                'documento_id' => $documento->id ?? null
            ]);
            throw $e;
        }
    }

    /**
     * Consulta el estado de un DTE
     * 
     * @param string $codigoGeneracion
     * @param string $tipoDte
     * @param string $ambiente
     * @return array Estado del documento
     * @throws \Exception Si hay errores en la consulta
     */
    public function consultarDTE(string $codigoGeneracion, string $tipoDte, string $ambiente): array
    {
        if (!$this->empresa) {
            throw new \Exception("Empresa no configurada para consultar DTE");
        }

        // Autenticar primero
        $auth = $this->auth($this->empresa);
        
        if (isset($auth['status']) && $auth['status'] == 'ERROR') {
            throw new \Exception($auth['body']['descripcionMsg'] ?? 'Error de autenticación');
        }

        $token = $auth['body']['token'] ?? null;
        if (!$token) {
            throw new \Exception("No se obtuvo token de autenticación");
        }

        $urlConsulta = $this->config['urls'][$ambiente == '01' ? 'produccion' : 'prueba']['consulta'];
        $nitEmisor = str_replace('-', '', $this->empresa->nit);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'SmartPyme',
                'Authorization' => $token
            ])->timeout(30)->post($urlConsulta, [
                'nitEmisor' => $nitEmisor,
                'tdte' => $tipoDte,
                'codigoGeneracion' => $codigoGeneracion
            ]);

            $data = $response->json();

            if ($response->failed()) {
                Log::error("Error al consultar DTE en MH", [
                    'response' => $data,
                    'codigo_generacion' => $codigoGeneracion
                ]);
                throw new \Exception("Error al consultar DTE: " . ($data['descripcionMsg'] ?? 'Error desconocido'));
            }

            return $data;
            
        } catch (\Exception $e) {
            Log::error("Excepción al consultar DTE", [
                'error' => $e->getMessage(),
                'codigo_generacion' => $codigoGeneracion
            ]);
            throw $e;
        }
    }

    /**
     * Genera el documento de anulación (debe ser implementado por clases hijas)
     * 
     * @param array $dte Documento original
     * @param mixed $documento Documento original
     * @return array Documento de anulación
     */
    abstract protected function generarDTEAnulado(array $dte, $documento): array;

    /**
     * Establece la empresa para operaciones
     * 
     * @param Empresa $empresa
     * @return void
     */
    protected function setEmpresa(Empresa $empresa): void
    {
        $this->empresa = $empresa;
    }
}
