<?php

namespace App\Exceptions;

use Exception;

class FacturacionException extends Exception
{
    public function __construct(
        string $message,
        public int $httpStatus = 400,
        public ?array $details = null
    ) {
        parent::__construct($message);
    }
}
