<?php

namespace App\Services\FacturacionElectronica\Contracts;

/**
 * Interface para implementaciones de facturación electrónica por país
 * 
 * Esta interface define el contrato que deben cumplir todas las implementaciones
 * de facturación electrónica, independientemente del país.
 * 
 * @package App\Services\FacturacionElectronica\Contracts
 */
interface FacturacionElectronicaInterface
{
    /**
     * Genera el documento tributario electrónico (DTE) según el tipo de documento
     * 
     * @param mixed $documento Venta, Compra, Devolucion, Gasto, etc.
     * @return array Estructura del DTE en formato JSON/XML según el país
     * @throws \Exception Si hay errores en la generación
     */
    public function generarDTE($documento): array;

    /**
     * Firma electrónicamente el documento generado
     * 
     * @param array $dte Documento a firmar
     * @return array Documento firmado
     * @throws \Exception Si hay errores en la firma
     */
    public function firmarDTE(array $dte): array;

    /**
     * Envía el documento firmado a la autoridad tributaria
     * 
     * @param array $dteFirmado Documento firmado
     * @param mixed $documento Documento original (Venta, Compra, etc.)
     * @return array Respuesta de la autoridad tributaria
     * @throws \Exception Si hay errores en el envío
     */
    public function enviarDTE(array $dteFirmado, $documento): array;

    /**
     * Anula un documento previamente emitido
     * 
     * @param array $dte Documento a anular
     * @param mixed $documento Documento original
     * @return array Documento de anulación
     * @throws \Exception Si hay errores en la anulación
     */
    public function anularDTE(array $dte, $documento): array;

    /**
     * Consulta el estado de un documento en la autoridad tributaria
     * 
     * @param string $codigoGeneracion Código de generación del documento
     * @param string $tipoDte Tipo de documento
     * @param string $ambiente Ambiente (prueba/producción)
     * @return array Estado del documento
     * @throws \Exception Si hay errores en la consulta
     */
    public function consultarDTE(string $codigoGeneracion, string $tipoDte, string $ambiente): array;

    /**
     * Obtiene la configuración específica del país
     * 
     * @return array Configuración con URLs, formatos, etc.
     */
    public function obtenerConfiguracion(): array;
}
