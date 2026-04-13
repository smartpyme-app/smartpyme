<?php

namespace App\Exceptions\CostaRica;

use RuntimeException;
use Throwable;

/**
 * Emisión FE Costa Rica fallida tras construir el payload enviado a DGT.
 * Permite devolver el JSON del comprobante intentado y el XML en español (XSD) en la respuesta HTTP (p. ej. 422).
 */
final class CostaRicaFeEmisionFallidaException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $documento  Payload pasado a setDocumentData (mismo que iría en dte.documento).
     * @param  array<string, mixed>|null  $detalleEstado  Respuesta normalizada de checkStatus (si hubo clave).
     * @param  string|null  $xmlComprobante  XML generado por dgt-xml-generator (etiquetas en español), sin firma.
     * @param  string|null  $xmlComprobanteFirmado  XML firmado si ya se firmó antes del fallo (p. ej. error de red al enviar); puede ser largo.
     */
    public function __construct(
        string $message,
        private readonly array $documento,
        private readonly ?string $clave = null,
        private readonly ?array $detalleEstado = null,
        private readonly ?string $xmlComprobante = null,
        private readonly ?string $xmlComprobanteFirmado = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDocumento(): array
    {
        return $this->documento;
    }

    public function getClave(): ?string
    {
        return $this->clave;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetalleEstado(): ?array
    {
        return $this->detalleEstado;
    }

    public function getXmlComprobante(): ?string
    {
        return $this->xmlComprobante;
    }

    public function getXmlComprobanteFirmado(): ?string
    {
        return $this->xmlComprobanteFirmado;
    }
}
