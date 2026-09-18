<?php

namespace App\Services\Compras;

/**
 * Compra o gasto que ya usa un código de generación (JSON SV / XML CR).
 */
final class CodigoGeneracionDuplicado
{
    public const TIPO_COMPRA = 'compra';

    public const TIPO_GASTO = 'gasto';

    public function __construct(
        public readonly string $tipo,
        public readonly int $id,
        public readonly ?string $referencia = null,
    ) {}

    public static function normalizar(?string $codigo): string
    {
        return strtoupper(trim((string) $codigo));
    }

    public function mensaje(): string
    {
        $label = $this->tipo === self::TIPO_GASTO ? 'un gasto' : 'una compra';
        $ref = trim((string) $this->referencia);
        $extra = $ref !== '' ? " (referencia {$ref})" : '';

        return "Ya está cargada {$label} con ese código de generación{$extra}.";
    }
}
