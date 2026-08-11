<?php

namespace App\Support\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use Carbon\Carbon;
use RuntimeException;

/**
 * Resuelve moneda, tipo de cambio y equivalentes en moneda funcional del documento CR
 * antes de persistirlo. Fuente de TC: BCCR vía CostaRicaTipoCambioService; sin fallback inventado.
 *
 * Spec: Docs/superpowers/specs/2026-08-03-cr-multimoneda-design.md §7.4
 */
final class DocumentoMoneda
{
    public const MONEDA_CRC = 'CRC';

    public const MONEDA_USD = 'USD';

    /** Campos que solo deben escribirse vía DocumentoMoneda::resolve (no mass-assignment del request). */
    public const CAMPOS_PERSISTIDOS = [
        'currency_code',
        'exchange_rate',
        'exchange_rate_date',
        'equivalent_total',
        'equivalent_iva',
    ];

    private const MONEDAS_SOPORTADAS = [self::MONEDA_CRC, self::MONEDA_USD];

    public function __construct(private readonly CostaRicaTipoCambioService $tipoCambioService) {}

    /**
     * @param  array<string, mixed>  $input  'currency_code' (CRC|USD, default CRC), 'total', 'iva' (montos
     *                                        nativos), 'exchange_rate' (solo se usa si $allowManualRate).
     * @param  Empresa  $empresa  Reservado para validaciones futuras (ej. inmutabilidad post-emisión, Task 4/5).
     *                            El flag `facturacion_fe.permitir_editar_tipo_cambio` (Task 3, SP-2097) ya se
     *                            resuelve en el caller (solo ventas, pre-emisión) y se pasa como $allowManualRate.
     * @return array{currency_code: string, exchange_rate: float, exchange_rate_date: string, equivalent_total: float, equivalent_iva: float}
     */
    public function resolve(array $input, Empresa $empresa, \DateTimeInterface $fechaDoc, bool $allowManualRate = false): array
    {
        $currencyCode = strtoupper(trim((string) ($input['currency_code'] ?? self::MONEDA_CRC)));
        if (! in_array($currencyCode, self::MONEDAS_SOPORTADAS, true)) {
            throw new RuntimeException("Moneda no soportada: {$currencyCode}. Solo CRC o USD en esta fase.");
        }

        $total = (float) ($input['total'] ?? 0);
        $iva = (float) ($input['iva'] ?? 0);
        $fecha = Carbon::instance(\DateTimeImmutable::createFromInterface($fechaDoc))->startOfDay();

        if ($currencyCode === self::MONEDA_CRC) {
            return [
                'currency_code' => self::MONEDA_CRC,
                'exchange_rate' => 1.0,
                'exchange_rate_date' => $fecha->toDateString(),
                'equivalent_total' => round($total, 5),
                'equivalent_iva' => round($iva, 5),
            ];
        }

        $manualRateProvisto = $allowManualRate
            && array_key_exists('exchange_rate', $input)
            && $input['exchange_rate'] !== null
            && $input['exchange_rate'] !== '';

        $rate = $manualRateProvisto
            ? (float) $input['exchange_rate']
            : $this->tipoCambioService->rateForDate($fecha);

        if ($rate <= 0) {
            throw new RuntimeException('Tipo de cambio inválido: debe ser mayor a cero.');
        }
        if ($rate === 1.0) {
            throw new RuntimeException('Tipo de cambio inválido para USD: no puede ser igual a 1 (use CRC).');
        }

        // Montos en BD = moneda empresa. Si la empresa es CRC, total/iva ya son colones.
        $monedaEmpresa = strtoupper(trim((string) ($empresa->moneda ?? self::MONEDA_CRC)));
        $montosYaEnCrc = $monedaEmpresa !== self::MONEDA_USD;

        return [
            'currency_code' => self::MONEDA_USD,
            'exchange_rate' => $rate,
            'exchange_rate_date' => $fecha->toDateString(),
            'equivalent_total' => round($montosYaEnCrc ? $total : $total * $rate, 5),
            'equivalent_iva' => round($montosYaEnCrc ? $iva : $iva * $rate, 5),
        ];
    }
}
