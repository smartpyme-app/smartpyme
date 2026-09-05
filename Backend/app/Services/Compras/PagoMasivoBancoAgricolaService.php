<?php

namespace App\Services\Compras;

use App\Models\Compras\Compra;
use Carbon\Carbon;

class PagoMasivoBancoAgricolaService
{
    public function generar(iterable $compras, string $fechaPago, string $formato = 'csv'): array
    {
        $formato = strtolower($formato) === 'txt' ? 'txt' : 'csv';
        $delimitador = $formato === 'txt' ? ';' : ',';
        $filas = [];
        $omitidos = [];

        foreach ($compras as $compra) {
            $resultado = $this->armarFila($compra, $fechaPago);
            if ($resultado['ok']) {
                $filas[] = implode($delimitador, $resultado['campos']);
            } else {
                $omitidos[] = $resultado['omitido'];
            }
        }

        $fecha = Carbon::parse($fechaPago)->format('Y-m-d');
        $contenido = $filas === [] ? '' : implode("\r\n", $filas) . "\r\n";

        return [
            'contenido' => $contenido,
            'filename' => 'pagos-banco-agricola-' . $fecha . '.' . $formato,
            'mime' => $formato === 'txt' ? 'text/plain; charset=UTF-8' : 'text/csv; charset=UTF-8',
            'incluidos' => count($filas),
            'omitidos' => $omitidos,
        ];
    }

    public function venceEnFecha(Compra $compra, string $fecha): bool
    {
        $objetivo = Carbon::parse($fecha)->toDateString();
        $vence = $compra->fecha_pago
            ? Carbon::parse($compra->fecha_pago)->toDateString()
            : Carbon::parse($compra->fecha)->addDays(30)->toDateString();

        return $vence === $objetivo;
    }

    private function armarFila(Compra $compra, string $fechaPago): array
    {
        $proveedor = $compra->proveedor;
        $nombre = $this->nombreProveedor($compra);
        $referencia = (string) ($compra->referencia ?? '');

        if ((int) $compra->cotizacion === 1) {
            return $this->omitido($nombre, $referencia, 'Las órdenes de compra no se incluyen');
        }

        $saldo = $this->saldo($compra);
        if ($saldo <= 0) {
            return $this->omitido($nombre, $referencia, 'El saldo pendiente es 0');
        }

        if (!$proveedor) {
            return $this->omitido($nombre, $referencia, 'Falta el número de cuenta');
        }

        if (!$this->esBancoAgricola($proveedor->banco ?? null)) {
            return $this->omitido($nombre, $referencia, 'El banco no es Banco Agrícola');
        }

        $cuenta = $this->normalizarCuenta($proveedor->numero_cuenta ?? '');
        if ($cuenta === null) {
            $motivo = trim((string) ($proveedor->numero_cuenta ?? '')) === ''
                ? 'Falta el número de cuenta'
                : 'La cuenta debe tener máximo 12 dígitos';
            return $this->omitido($nombre, $referencia, $motivo);
        }

        $concepto = 'Pago de proveedor ' . Carbon::parse($fechaPago)->format('d-m-Y');
        if (mb_strlen($concepto) > 80) {
            $concepto = mb_substr($concepto, 0, 80);
        }

        return [
            'ok' => true,
            'campos' => [
                $cuenta,
                $this->sanitizarCampo($nombre),
                '',
                number_format($saldo, 2, '.', ''),
                $this->sanitizarCampo($referencia),
                $this->sanitizarCampo($concepto),
                $this->normalizarCorreo($proveedor->correo ?? null),
            ],
        ];
    }

    public function esBancoAgricola(?string $banco): bool
    {
        if ($banco === null || trim($banco) === '') {
            return false;
        }

        $sinTilde = strtr(mb_strtolower($banco), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        ]);

        return str_contains($sinTilde, 'agricola');
    }

    public function normalizarCuenta(?string $cuenta): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $cuenta) ?? '';
        if ($digitos === '') {
            return null;
        }
        if (strlen($digitos) > 12) {
            return null;
        }

        return str_pad($digitos, 12, '0', STR_PAD_LEFT);
    }

    private function saldo(Compra $compra): float
    {
        $abonado = (float) ($compra->abonos_sum_total ?? 0);
        $devoluciones = (float) ($compra->devoluciones_sum_total ?? 0);

        return round((float) $compra->total - $abonado - $devoluciones, 2);
    }

    private function nombreProveedor(Compra $compra): string
    {
        $proveedor = $compra->proveedor;
        if (!$proveedor) {
            return (string) ($compra->nombre_proveedor ?? 'Consumidor Final');
        }

        if (($proveedor->tipo ?? '') === 'Empresa') {
            return trim((string) $proveedor->nombre_empresa);
        }

        return trim(trim((string) $proveedor->nombre) . ' ' . trim((string) ($proveedor->apellido ?? '')));
    }

    private function sanitizarCampo(string $valor): string
    {
        $limpio = str_replace([',', ';'], ' ', $valor);
        $limpio = preg_replace('/\s+/', ' ', $limpio) ?? $limpio;

        return trim($limpio);
    }

    private function normalizarCorreo(?string $correo): string
    {
        $correo = trim((string) $correo);
        if ($correo === '' || mb_strlen($correo) > 50 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        return $correo;
    }

    private function omitido(string $proveedor, string $referencia, string $motivo): array
    {
        return [
            'ok' => false,
            'omitido' => [
                'proveedor' => $proveedor,
                'referencia' => $referencia,
                'motivo' => $motivo,
            ],
        ];
    }
}
