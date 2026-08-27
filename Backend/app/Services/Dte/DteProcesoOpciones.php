<?php

namespace App\Services\Dte;

use InvalidArgumentException;

/**
 * Opciones del proceso manual de un DTE (pago, crédito, retaceo, productos).
 */
final class DteProcesoOpciones
{
    /**
     * @param list<array{index: int, id_producto: int, cantidad: float}> $lineMappings
     */
    public function __construct(
        public readonly ?string $formaPago = null,
        public readonly bool $credito = false,
        public readonly ?string $fechaPago = null,
        public readonly ?string $detalleBanco = null,
        public readonly bool $esRetaceo = false,
        public readonly array $lineMappings = [],
    ) {}

    public static function fromArray(array $input): self
    {
        $mappings = [];
        foreach ($input['line_mappings'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mappings[] = [
                'index' => (int) ($row['index'] ?? 0),
                'id_producto' => (int) ($row['id_producto'] ?? 0),
                'cantidad' => (float) ($row['cantidad'] ?? 0),
            ];
        }

        $formaPago = isset($input['forma_pago']) ? trim((string) $input['forma_pago']) : null;
        $fechaPago = isset($input['fecha_pago']) ? trim((string) $input['fecha_pago']) : null;
        $detalleBanco = isset($input['detalle_banco']) ? trim((string) $input['detalle_banco']) : null;

        return new self(
            formaPago: $formaPago !== '' ? $formaPago : null,
            credito: filter_var($input['credito'] ?? false, FILTER_VALIDATE_BOOLEAN),
            fechaPago: $fechaPago !== '' ? $fechaPago : null,
            detalleBanco: $detalleBanco !== '' ? $detalleBanco : null,
            esRetaceo: filter_var($input['es_retaceo'] ?? false, FILTER_VALIDATE_BOOLEAN),
            lineMappings: $mappings,
        );
    }

    public function omitirCompraPendienteClasificacion(string $status): bool
    {
        return $status === 'pendiente_clasificacion' && $this->lineMappings === [];
    }

    public function estadoCompra(): string
    {
        return $this->credito ? 'Pendiente' : 'Pagada';
    }

    public function estadoGasto(): string
    {
        return $this->credito ? 'Pendiente' : 'Confirmado';
    }

    /**
     * @return array<int, array{id_producto: int, cantidad: float}>
     */
    public function mappingPorIndex(): array
    {
        $porIndex = [];
        foreach ($this->lineMappings as $row) {
            $porIndex[$row['index']] = [
                'id_producto' => $row['id_producto'],
                'cantidad' => $row['cantidad'],
            ];
        }

        return $porIndex;
    }

    public function validarPago(): void
    {
        if ($this->credito && ($this->fechaPago === null || $this->fechaPago === '')) {
            throw new InvalidArgumentException('La fecha de pago es obligatoria en crédito.');
        }

        if ($this->requiereBanco() && ($this->detalleBanco === null || $this->detalleBanco === '')) {
            throw new InvalidArgumentException('Seleccione el banco para esta forma de pago.');
        }
    }

    public function validarLineasCompra(int $totalLineas): void
    {
        if ($totalLineas <= 0) {
            return;
        }

        $porIndex = $this->mappingPorIndex();
        for ($i = 0; $i < $totalLineas; $i++) {
            $row = $porIndex[$i] ?? null;
            if ($row === null || $row['id_producto'] <= 0) {
                throw new InvalidArgumentException('Debe vincular un producto en todas las líneas de la compra.');
            }
            if ($row['cantidad'] <= 0) {
                throw new InvalidArgumentException('La cantidad de cada línea debe ser mayor a 0.');
            }
        }
    }

    public function requiereBanco(): bool
    {
        $forma = $this->formaPago ?? 'Efectivo';

        return $forma !== 'Efectivo' && $forma !== 'Wompi';
    }

    /**
     * @return array{forma_pago: string, credito: bool}
     */
    public static function pagoSugeridoDesdeJson(array $jsonData): array
    {
        $resumen = is_array($jsonData['resumen'] ?? null) ? $jsonData['resumen'] : [];
        $codigo = $resumen['pagos'][0]['codigo'] ?? null;
        $mapa = [
            '01' => 'Efectivo',
            '02' => 'Tarjeta de Crédito',
            '03' => 'Tarjeta de Débito',
            '04' => 'Cheque',
            '05' => 'Transferencia',
            '06' => 'Crédito',
            '07' => 'Tarjeta de regalo',
            '08' => 'Dinero electrónico',
            '99' => 'Otros',
        ];
        $formaPago = is_string($codigo) && isset($mapa[$codigo]) ? $mapa[$codigo] : 'Efectivo';
        $credito = $codigo === '06' || (int) ($resumen['condicionOperacion'] ?? 0) === 2;

        return [
            'forma_pago' => $formaPago,
            'credito' => $credito,
        ];
    }
}
