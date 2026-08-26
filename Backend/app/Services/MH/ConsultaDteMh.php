<?php

namespace App\Services\MH;

class ConsultaDteMh
{
    public static function payload(string $nit, string $tdte, string $codigoGeneracion): array
    {
        return [
            'nitEmisor' => str_replace('-', '', $nit),
            'tdte' => $tdte,
            'codigoGeneracion' => $codigoGeneracion,
        ];
    }

    /**
     * @param  mixed  $body
     * @return mixed
     */
    public static function adaptarRespuesta($body)
    {
        if (! is_array($body)) {
            return $body;
        }

        if (! empty($body['selloRecibido']) && empty($body['selloVal'])) {
            $body['selloVal'] = $body['selloRecibido'];
        }

        return $body;
    }
}
