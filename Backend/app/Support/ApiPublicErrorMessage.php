<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Throwable;

/** Mensajes seguros para API (sin filtrar SQL ni detalles internos). */
final class ApiPublicErrorMessage
{
    public static function fromQueryException(QueryException $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Out of range value')) {
            return 'Un monto o cantidad supera el límite permitido. Verifique los valores o contacte a soporte.';
        }
        if (str_contains($msg, 'Duplicate entry')) {
            return 'Ya existe un registro con esos datos.';
        }
        if (str_contains($msg, 'Unknown column') || str_contains($msg, "doesn't exist")) {
            return 'El sistema requiere una actualización. Contacte a soporte.';
        }
        if (str_contains($msg, 'foreign key constraint')) {
            return 'No se puede completar la operación porque el registro está relacionado con otros datos.';
        }

        return 'No se pudo guardar la información. Intente de nuevo o contacte a soporte.';
    }

    public static function fromThrowable(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            return self::fromQueryException($e);
        }

        $msg = trim($e->getMessage());
        if ($msg === '' || self::looksLikeTechnicalLeak($msg)) {
            return 'Ocurrió un error inesperado. Intente de nuevo o contacte a soporte.';
        }

        return $msg;
    }

    public static function looksLikeTechnicalLeak(string $message): bool
    {
        $needles = [
            'SQLSTATE',
            'Connection:',
            'SQL:',
            'insert into `',
            'update `',
            'delete from `',
            'Integrity constraint violation',
            'PDOException',
            'Stack trace',
        ];

        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function sanitizeString(?string $message): string
    {
        $msg = trim((string) $message);
        if ($msg === '') {
            return 'Ocurrió un error inesperado. Intente de nuevo o contacte a soporte.';
        }
        if (self::looksLikeTechnicalLeak($msg)) {
            if (str_contains($msg, 'Out of range value')) {
                return 'Un monto o cantidad supera el límite permitido. Verifique los valores o contacte a soporte.';
            }

            return 'No se pudo completar la operación. Intente de nuevo o contacte a soporte.';
        }

        return $msg;
    }
}
