<?php

namespace App\Services\Moneda;

use App\Models\Admin\Empresa;
use App\Models\PaisConfiguracion;
use App\Services\FacturacionElectronica\CostaRica\CostaRicaTipoCambioService;
use App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver;
use App\Support\Admin\MonedaDefaultPorPais;
use Carbon\Carbon;
use RuntimeException;

/**
 * Config y resolución de TC por país (`pais_configuracion` módulo moneda).
 * CR (fuente=api/bccr) → BCCR; HN y demás (fuente=manual) → rate_manual / override.
 */
final class MonedaPaisService
{
    public function __construct(private readonly CostaRicaTipoCambioService $bccr) {}

    /** @return array<string, mixed> */
    public function configForEmpresa(Empresa $empresa): array
    {
        $pais = FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);
        $row = PaisConfiguracion::query()
            ->pais($pais)
            ->modulo(PaisConfiguracion::MODULO_MONEDA)
            ->first();

        if ($row && is_array($row->configuracion)) {
            return array_merge(MonedaDefaultPorPais::plantilla($pais), $row->configuracion);
        }

        return MonedaDefaultPorPais::plantilla($pais);
    }

    public function monedaFuncional(Empresa $empresa): string
    {
        $cfg = $this->configForEmpresa($empresa);

        return strtoupper((string) ($cfg['moneda_funcional'] ?? 'USD'));
    }

    /**
     * Preview TC para UI (fecha del documento).
     *
     * @return array{rate: float|null, date: string, fuente: string, moneda_funcional: string, monedas_documento: list<string>, permitir_editar: bool, error: string|null}
     */
    public function preview(Empresa $empresa, ?\DateTimeInterface $date = null): array
    {
        $cfg = $this->configForEmpresa($empresa);
        $funcional = strtoupper((string) ($cfg['moneda_funcional'] ?? 'USD'));
        $monedas = array_values(array_map('strtoupper', $cfg['monedas_documento'] ?? [$funcional, 'USD']));
        $fuente = (string) ($cfg['fuente'] ?? 'manual');
        $permitirEditar = (bool) ($cfg['permitir_editar'] ?? false);
        $day = Carbon::instance(
            \DateTimeImmutable::createFromInterface($date ?? now())
        )->startOfDay();

        try {
            $rate = $this->rateForDate($empresa, $cfg, $day, null, false);

            return [
                'rate' => $rate,
                'date' => $day->toDateString(),
                'fuente' => $fuente,
                'moneda_funcional' => $funcional,
                'monedas_documento' => $monedas,
                'permitir_editar' => $permitirEditar,
                'error' => null,
            ];
        } catch (RuntimeException $e) {
            return [
                'rate' => null,
                'date' => $day->toDateString(),
                'fuente' => $fuente,
                'moneda_funcional' => $funcional,
                'monedas_documento' => $monedas,
                'permitir_editar' => $permitirEditar,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{currency_code: string, exchange_rate: float, exchange_rate_date: string, equivalent_total: float, equivalent_iva: float}
     */
    public function resolveDocumento(
        Empresa $empresa,
        array $input,
        \DateTimeInterface $fechaDoc,
        bool $allowManualRate = false
    ): array {
        $cfg = $this->configForEmpresa($empresa);
        $funcional = strtoupper((string) ($cfg['moneda_funcional'] ?? 'USD'));
        $monedas = array_map('strtoupper', $cfg['monedas_documento'] ?? [$funcional]);
        $fecha = Carbon::instance(\DateTimeImmutable::createFromInterface($fechaDoc))->startOfDay();

        $currencyCode = strtoupper(trim((string) ($input['currency_code'] ?? $funcional)));
        if (! in_array($currencyCode, $monedas, true)) {
            throw new RuntimeException("Moneda no soportada para este país: {$currencyCode}.");
        }

        $total = (float) ($input['total'] ?? 0);
        $iva = (float) ($input['iva'] ?? 0);

        if ($currencyCode === $funcional) {
            return [
                'currency_code' => $funcional,
                'exchange_rate' => 1.0,
                'exchange_rate_date' => $fecha->toDateString(),
                'equivalent_total' => round($total, 5),
                'equivalent_iva' => round($iva, 5),
            ];
        }

        // Moneda extranjera (p.ej. USD): montos en BD = moneda empresa/funcional;
        // TC = unidades de moneda funcional por 1 USD.
        $manualProvisto = $allowManualRate
            && array_key_exists('exchange_rate', $input)
            && $input['exchange_rate'] !== null
            && $input['exchange_rate'] !== '';

        $rate = $this->rateForDate(
            $empresa,
            $cfg,
            $fecha,
            $manualProvisto ? (float) $input['exchange_rate'] : null,
            $allowManualRate
        );

        if ($rate <= 0) {
            throw new RuntimeException('Tipo de cambio inválido: debe ser mayor a cero.');
        }
        if ($rate === 1.0) {
            throw new RuntimeException('Tipo de cambio inválido para moneda extranjera: no puede ser 1.');
        }

        // Misma semántica que DocumentoMoneda CR: totales ya van en moneda empresa.
        return [
            'currency_code' => $currencyCode,
            'exchange_rate' => $rate,
            'exchange_rate_date' => $fecha->toDateString(),
            'equivalent_total' => round($total, 5),
            'equivalent_iva' => round($iva, 5),
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function rateForDate(
        Empresa $empresa,
        array $cfg,
        Carbon $day,
        ?float $override,
        bool $allowManual
    ): float {
        if ($allowManual && $override !== null && $override > 0 && $override !== 1.0) {
            return $override;
        }

        $fuente = (string) ($cfg['fuente'] ?? 'manual');
        $provider = $cfg['api']['provider'] ?? null;

        if ($fuente === 'api' && $provider === 'bccr') {
            return $this->bccr->rateForDate($day);
        }

        $cached = $cfg['rate_del_dia'] ?? null;
        if (is_array($cached)
            && ($cached['date'] ?? null) === $day->toDateString()
            && (float) ($cached['rate'] ?? 0) > 0
        ) {
            return (float) $cached['rate'];
        }

        $manual = (float) ($cfg['rate_manual'] ?? 0);
        if ($manual > 0) {
            return $manual;
        }

        $paisCode = FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);
        throw new RuntimeException(
            'No hay tipo de cambio configurado para '.$paisCode
            .' (fuente='.$fuente.'). Configure rate_manual o la API del país.'
        );
    }
}
