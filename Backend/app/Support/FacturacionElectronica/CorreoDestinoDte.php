<?php

namespace App\Support\FacturacionElectronica;

/**
 * Destinatario de un reenvío de DTE. Un correo escrito reemplaza al del registro; vacío lo conserva.
 */
final class CorreoDestinoDte
{
    public static function resolver(?string $correoRegistro, mixed $correoEscrito): ?string
    {
        $escrito = trim((string) $correoEscrito);
        if ($escrito !== '') {
            return $escrito;
        }

        return $correoRegistro;
    }
}
