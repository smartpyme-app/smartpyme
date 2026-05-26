<?php

namespace App\Services\Contabilidad\NotasEstadosFinancieros;

class NotasEstadosFinancierosValidacionService
{
    private const TOLERANCIA = 0.02;

    /**
     * @param  array<int, array<string, mixed>>  $notas
     * @param  array<string, float>  $balanceLines
     * @param  array<string, mixed>  $estadoResultados
     * @return list<array<string, mixed>>
     */
    public function validar(array $notas, array $balanceLines, array $estadoResultados): array
    {
        $validaciones = [];

        if (isset($notas[4])) {
            $totalNota = (float) ($notas[4]['contenido']['total_efectivo'] ?? 0);
            $totalBalance = (float) ($balanceLines['efectivo_equivalentes'] ?? 0);
            $validaciones[] = $this->regla(
                'nota_4_efectivo',
                'Nota 4: total efectivo = Balance efectivo y equivalentes',
                $totalNota,
                $totalBalance
            );
        }

        if (isset($notas[7])) {
            $netoNota = (float) ($notas[7]['contenido']['valor_libros_neto'] ?? 0);
            $netoBalance = (float) ($balanceLines['propiedad_planta_equipo'] ?? 0)
                + (float) ($balanceLines['depreciacion_acumulada'] ?? 0);
            $validaciones[] = $this->regla(
                'nota_7_ppe_neta',
                'Nota 7: valor en libros neto = Balance PPE neta',
                $netoNota,
                $netoBalance
            );

            $depNota = (float) ($notas[7]['contenido']['depreciacion_cargada_anio'] ?? 0);
            $depEr = (float) ($estadoResultados['L']['gasto_venta_deprec'] ?? 0)
                + (float) ($estadoResultados['L']['gasto_admin_deprec'] ?? 0);
            $validaciones[] = $this->regla(
                'nota_7_depreciacion_er',
                'Nota 7: depreciación del año = ER gasto depreciación',
                $depNota,
                $depEr
            );
        }

        if (isset($notas[10])) {
            $isrNota = (float) ($notas[10]['contenido']['isr_neto_pagar'] ?? 0);
            $isrBalance = (float) ($balanceLines['isr_por_pagar'] ?? 0);
            $validaciones[] = $this->regla(
                'nota_10_isr',
                'Nota 10: ISR neto a pagar = Balance ISR por pagar',
                $isrNota,
                $isrBalance
            );
        }

        if (isset($notas[12])) {
            $reservaNota = (float) ($notas[12]['contenido']['reserva_legal_cierre'] ?? 0);
            $reservaBalance = (float) ($balanceLines['reserva_legal'] ?? 0);
            $validaciones[] = $this->regla(
                'nota_12_reserva_legal',
                'Nota 12: reserva legal cierre = Balance reserva legal',
                $reservaNota,
                $reservaBalance
            );
        }

        if (isset($notas[11])) {
            $provNota = (float) ($notas[11]['contenido']['saldo_provision_indemnizacion'] ?? 0);
            $provBalance = (float) ($balanceLines['provision_indemnizaciones'] ?? 0);
            $validaciones[] = $this->regla(
                'nota_11_indemnizacion',
                'Nota 11: saldo provisión indemnización = Balance provisión indemnizaciones',
                $provNota,
                $provBalance
            );
        }

        return $validaciones;
    }

    /**
     * @return array<string, mixed>
     */
    private function regla(string $clave, string $descripcion, float $valorNota, float $valorReferencia): array
    {
        $diferencia = round($valorNota - $valorReferencia, 2);
        $cuadra = abs($diferencia) <= self::TOLERANCIA;

        return [
            'clave' => $clave,
            'descripcion' => $descripcion,
            'valor_nota' => $valorNota,
            'valor_referencia' => $valorReferencia,
            'diferencia' => $diferencia,
            'cuadra' => $cuadra,
        ];
    }
}
