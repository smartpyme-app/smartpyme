<?php

namespace App\Exceptions;

use Exception;

class PartidaDetalleSinProductoException extends Exception
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly array $payload,
        int $status = 422,
    ) {
        $message = (string) ($payload['error'] ?? 'Hay líneas de venta sin producto o servicio asignado.');
        parent::__construct($message, $status);
    }

    public function statusCode(): int
    {
        $code = $this->getCode();

        return $code >= 400 && $code < 600 ? $code : 422;
    }
}
