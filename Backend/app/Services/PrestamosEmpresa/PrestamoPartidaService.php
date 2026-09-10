<?php

namespace App\Services\PrestamosEmpresa;

use App\Models\Admin\FormaDePago;
use App\Models\Bancos\Cuenta;
use App\Models\Contabilidad\Catalogo\Cuenta as CuentaContable;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\PrestamosEmpresa\PrestamoEmpresa;
use App\Models\PrestamosEmpresa\PrestamoPago;
use Exception;
use Illuminate\Support\Facades\DB;

class PrestamoPartidaService
{
    public const REF_DESEMBOLSO = 'Desembolso de Prestamo';
    public const REF_PAGO = 'Pago de Prestamo';

    public function desembolso(PrestamoEmpresa $prestamo): void
    {
        if (!$prestamo->generar_asiento_desembolso) {
            return;
        }
        if (!$this->esAuto((int) $prestamo->id_empresa)) {
            return;
        }

        Partida::assertNoExisteParaOrigen(self::REF_DESEMBOLSO, $prestamo->id);

        $config = $this->config((int) $prestamo->id_empresa);
        $pasivo = $this->cuentaPasivo($config, $prestamo->clasificacion);
        $banco = $this->cuentaBancoPrestamo($prestamo);
        $concepto = 'Desembolso préstamo '.$prestamo->acreedor;

        DB::transaction(function () use ($prestamo, $pasivo, $banco, $concepto) {
            $partida = Partida::create([
                'fecha' => $prestamo->fecha_desembolso,
                'tipo' => 'Diario',
                'concepto' => $concepto,
                'estado' => 'Pendiente',
                'referencia' => self::REF_DESEMBOLSO,
                'id_referencia' => $prestamo->id,
                'id_usuario' => $prestamo->id_usuario,
                'id_empresa' => $prestamo->id_empresa,
            ]);

            Detalle::create([
                'id_cuenta' => $banco->id,
                'codigo' => $banco->codigo,
                'nombre_cuenta' => $banco->nombre,
                'concepto' => $concepto,
                'debe' => $prestamo->monto,
                'haber' => null,
                'saldo' => 0,
                'id_partida' => $partida->id,
            ]);
            Detalle::create([
                'id_cuenta' => $pasivo->id,
                'codigo' => $pasivo->codigo,
                'nombre_cuenta' => $pasivo->nombre,
                'concepto' => $concepto,
                'debe' => null,
                'haber' => $prestamo->monto,
                'saldo' => 0,
                'id_partida' => $partida->id,
            ]);
        });
    }

    public function pago(PrestamoEmpresa $prestamo, PrestamoPago $pago): void
    {
        if (!$this->esAuto((int) $prestamo->id_empresa)) {
            return;
        }

        Partida::assertNoExisteParaOrigen(self::REF_PAGO, $pago->id);

        $config = $this->config((int) $prestamo->id_empresa);
        $pasivo = $this->cuentaPasivo($config, $prestamo->clasificacion);
        $banco = $this->cuentaBancoPago($prestamo, $pago->metodo);
        $concepto = 'Pago préstamo '.$prestamo->acreedor;

        DB::transaction(function () use ($prestamo, $pago, $config, $pasivo, $banco, $concepto) {
            $partida = Partida::create([
                'fecha' => $pago->fecha,
                'tipo' => 'Egreso',
                'concepto' => $concepto,
                'estado' => 'Pendiente',
                'referencia' => self::REF_PAGO,
                'id_referencia' => $pago->id,
                'id_usuario' => $pago->id_usuario,
                'id_empresa' => $prestamo->id_empresa,
            ]);

            if ((float) $pago->capital > 0) {
                Detalle::create([
                    'id_cuenta' => $pasivo->id,
                    'codigo' => $pasivo->codigo,
                    'nombre_cuenta' => $pasivo->nombre,
                    'concepto' => $concepto.' (capital)',
                    'debe' => $pago->capital,
                    'haber' => null,
                    'saldo' => 0,
                    'id_partida' => $partida->id,
                ]);
            }

            if ((float) $pago->interes > 0) {
                $gasto = $this->cuentaGasto($config);
                Detalle::create([
                    'id_cuenta' => $gasto->id,
                    'codigo' => $gasto->codigo,
                    'nombre_cuenta' => $gasto->nombre,
                    'concepto' => $concepto.' (interés)',
                    'debe' => $pago->interes,
                    'haber' => null,
                    'saldo' => 0,
                    'id_partida' => $partida->id,
                ]);
            }

            Detalle::create([
                'id_cuenta' => $banco->id,
                'codigo' => $banco->codigo,
                'nombre_cuenta' => $banco->nombre,
                'concepto' => $concepto,
                'debe' => null,
                'haber' => $pago->monto,
                'saldo' => 0,
                'id_partida' => $partida->id,
            ]);
        });
    }

    public function revertirPago(int $idPago): void
    {
        $partidas = Partida::withoutGlobalScopes()
            ->where('referencia', self::REF_PAGO)
            ->where('id_referencia', $idPago)
            ->get();

        foreach ($partidas as $partida) {
            Detalle::where('id_partida', $partida->id)->delete();
            $partida->delete();
        }
    }

    private function esAuto(int $empresaId): bool
    {
        $config = Configuracion::withoutGlobalScopes()->where('id_empresa', $empresaId)->first();

        return $config && $config->generar_partidas === 'Auto';
    }

    private function config(int $empresaId): Configuracion
    {
        $config = Configuracion::withoutGlobalScopes()->where('id_empresa', $empresaId)->first();
        if (!$config) {
            throw new Exception('No se encontró la configuración contable', 400);
        }

        return $config;
    }

    private function cuentaPasivo(Configuracion $config, string $clasificacion): CuentaContable
    {
        $id = $clasificacion === 'largo'
            ? $config->id_cuenta_prestamos_largo
            : $config->id_cuenta_prestamos_corto;
        if (!$id) {
            throw new Exception('Configure las cuentas de préstamos (corto/largo plazo).', 400);
        }
        $cuenta = CuentaContable::find($id);
        if (!$cuenta) {
            throw new Exception('No se encontró la cuenta de préstamos por pagar.', 400);
        }

        return $cuenta;
    }

    private function cuentaGasto(Configuracion $config): CuentaContable
    {
        if (!$config->id_cuenta_gastos_financieros) {
            throw new Exception('Configure la cuenta de gastos financieros.', 400);
        }
        $cuenta = CuentaContable::find($config->id_cuenta_gastos_financieros);
        if (!$cuenta) {
            throw new Exception('No se encontró la cuenta de gastos financieros.', 400);
        }

        return $cuenta;
    }

    private function cuentaBancoPrestamo(PrestamoEmpresa $prestamo): CuentaContable
    {
        if (!$prestamo->id_cuenta_banco) {
            throw new Exception('Seleccione la cuenta de caja/banco del desembolso.', 400);
        }
        $bancaria = Cuenta::withoutGlobalScopes()->find($prestamo->id_cuenta_banco);
        if (!$bancaria || !$bancaria->id_cuenta_contable) {
            throw new Exception('La cuenta bancaria no tiene cuenta contable.', 400);
        }
        $cuenta = CuentaContable::find($bancaria->id_cuenta_contable);
        if (!$cuenta) {
            throw new Exception('No se encontró la cuenta contable del banco.', 400);
        }

        return $cuenta;
    }

    private function cuentaBancoPago(PrestamoEmpresa $prestamo, ?string $metodo): CuentaContable
    {
        if ($metodo) {
            $forma = FormaDePago::with('banco')->where('nombre', $metodo)->first();
            if ($forma?->banco?->id_cuenta_contable) {
                $cuenta = CuentaContable::find($forma->banco->id_cuenta_contable);
                if ($cuenta) {
                    return $cuenta;
                }
            }
        }

        return $this->cuentaBancoPrestamo($prestamo);
    }
}
