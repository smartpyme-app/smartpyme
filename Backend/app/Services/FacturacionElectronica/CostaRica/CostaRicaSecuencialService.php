<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CostaRicaSecuencialService
{
    private const CAMPOS_PERMITIDOS = [
        'cr_secuencial_factura',
        'cr_secuencial_nota_credito',
        'cr_secuencial_nota_debito',
        'cr_secuencial_tiquete',
    ];

    public function siguienteFactura(Empresa $empresa): int
    {
        return $this->siguiente($empresa, 'cr_secuencial_factura');
    }

    public function siguienteNotaCredito(Empresa $empresa): int
    {
        return $this->siguiente($empresa, 'cr_secuencial_nota_credito');
    }

    public function siguienteNotaDebito(Empresa $empresa): int
    {
        return $this->siguiente($empresa, 'cr_secuencial_nota_debito');
    }

    public function siguienteTiquete(Empresa $empresa): int
    {
        return $this->siguiente($empresa, 'cr_secuencial_tiquete');
    }

    public function siguiente(Empresa $empresa, string $campo): int
    {
        if (! in_array($campo, self::CAMPOS_PERMITIDOS, true)) {
            throw new InvalidArgumentException('Campo de secuencial CR no válido.');
        }

        return (int) DB::transaction(function () use ($empresa, $campo) {
            /** @var Empresa $bloqueada */
            $bloqueada = Empresa::query()->whereKey($empresa->id)->lockForUpdate()->firstOrFail();

            $config = $bloqueada->custom_empresa;
            if (! is_array($config)) {
                $config = [];
            }
            if (! isset($config['facturacion_fe']) || ! is_array($config['facturacion_fe'])) {
                $config['facturacion_fe'] = [];
            }

            $actual = (int) ($config['facturacion_fe'][$campo] ?? 0);
            $siguiente = $actual + 1;
            $config['facturacion_fe'][$campo] = $siguiente;

            $bloqueada->custom_empresa = $config;
            $bloqueada->save();

            return $siguiente;
        });
    }
}
