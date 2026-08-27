<?php

namespace App\Services\CreditosClientes;

use App\Models\CreditosClientes\CreditoContrato;
use App\Models\CreditosClientes\CreditoCuota;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CrearCreditoContratoService
{
    public function crear(User $user, array $data): CreditoContrato
    {
        if ($user->tipo === 'Ventas Limitado') {
            throw new AccessDeniedHttpException(
                'Los usuarios de tipo "Ventas Limitado" no pueden crear créditos.'
            );
        }

        $cliente = Cliente::findOrFail($data['id_cliente']);
        $monto = round((float) $data['monto'], 2);
        $saldoUsado = $this->saldoUsado(
            (int) $cliente->id,
            isset($data['excluir_id_venta']) ? (int) $data['excluir_id_venta'] : null
        );
        $limite = $cliente->limite_credito !== null ? (float) $cliente->limite_credito : null;

        if (!CupoCredito::cabe($limite, $saldoUsado, $monto)) {
            throw ValidationException::withMessages([
                'monto' => 'El saldo más este monto supera el límite de crédito del cliente.',
            ]);
        }

        try {
            $plan = PlanCuotasIguales::generar(
                $monto,
                (int) $data['n_cuotas'],
                $data['fecha_inicio']
            );
            if (!empty($data['cuotas']) && is_array($data['cuotas'])) {
                $plan = PlanCuotasIguales::aplicarMontos($plan, $data['cuotas'], $monto);
            }
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'cuotas' => $e->getMessage(),
            ]);
        }

        return DB::transaction(function () use ($user, $cliente, $data, $monto, $plan) {
            $contrato = CreditoContrato::create([
                'id_empresa' => $user->id_empresa,
                'id_cliente' => $cliente->id,
                'id_usuario' => $user->id,
                'tipo' => $data['tipo'],
                'monto' => $monto,
                'n_cuotas' => (int) $data['n_cuotas'],
                'fecha_inicio' => $data['fecha_inicio'],
                'periodicidad' => 'mensual',
                'tasa_interes' => $data['tasa_interes'] ?? 0,
                'tasa_mora' => $data['tasa_mora'] ?? 0,
                'concepto' => $data['concepto'] ?? null,
                'estado' => 'activo',
            ]);

            foreach ($plan as $cuota) {
                $contrato->cuotas()->create([
                    'numero' => $cuota['numero'],
                    'fecha_vencimiento' => $cuota['fecha_vencimiento'],
                    'monto' => $cuota['monto'],
                    'estado' => CreditoCuota::ESTADO_PROGRAMADA,
                    'id_venta' => null,
                ]);
            }

            return $contrato->load(['cuotas', 'cliente']);
        });
    }

    public function crearDesdeVenta(User $user, array $data, Venta $venta): CreditoContrato
    {
        $data['id_cliente'] = $data['id_cliente'] ?? $venta->id_cliente;
        $data['excluir_id_venta'] = $venta->id;

        if ((int) $venta->id_cliente !== (int) $data['id_cliente']) {
            throw new \App\Exceptions\FacturacionException(
                'El cliente de la venta no coincide con el crédito.',
                422
            );
        }

        $monto = round((float) $data['monto'], 2);
        $primera = null;
        if (!empty($data['cuotas'][0]['monto'])) {
            $primera = round((float) $data['cuotas'][0]['monto'], 2);
        } else {
            $planIgual = PlanCuotasIguales::generar($monto, (int) $data['n_cuotas'], $data['fecha_inicio'] ?? '2000-01-01');
            $primera = $planIgual[0]['monto'];
        }
        if (!CuotaInicialFactura::coincide((float) $venta->total, $primera)) {
            throw new \App\Exceptions\FacturacionException(
                'El total de la venta debe ser la primera cuota del crédito.',
                422
            );
        }

        try {
            $contrato = $this->crear($user, $data);
        } catch (ValidationException $e) {
            throw new \App\Exceptions\FacturacionException(
                (string) $e->validator->errors()->first(),
                422
            );
        } catch (AccessDeniedHttpException $e) {
            throw new \App\Exceptions\FacturacionException($e->getMessage(), 403);
        }

        $cuota1 = $contrato->cuotas->firstWhere('numero', 1);
        if (!$cuota1) {
            throw new \App\Exceptions\FacturacionException('No se generó la primera cuota.', 422);
        }

        app(VincularCuotaVentaService::class)->vincular((int) $cuota1->id, $venta);

        $venta->estado = 'Pagada';
        $venta->save();

        app(ClonarVentasCuotasCredito::class)->clonarRestantes($venta, $contrato->fresh(['cuotas']));

        return $contrato->fresh(['cuotas', 'cliente']);
    }

    public function saldoUsado(int $idCliente, ?int $excluirVentaId = null): float
    {
        $ventasPendientes = Venta::where('id_cliente', $idCliente)
            ->where('estado', 'Pendiente')
            ->where(function ($q) {
                $q->where('cotizacion', 0)->orWhereNull('cotizacion');
            })
            ->when($excluirVentaId, fn ($q) => $q->where('id', '!=', $excluirVentaId))
            ->withSum(['abonos' => fn ($q) => $q->where('estado', 'Confirmado')], 'total')
            ->withSum(['devoluciones' => fn ($q) => $q->where('enable', 1)], 'total')
            ->get();

        $saldoVentas = $ventasPendientes->sum(function ($v) {
            $abonos = $v->abonos_sum_total ?? 0;
            $devoluciones = $v->devoluciones_sum_total ?? 0;

            return round($v->total - $abonos - $devoluciones, 2);
        });

        $cuotasProgramadas = CreditoCuota::query()
            ->where('estado', CreditoCuota::ESTADO_PROGRAMADA)
            ->whereNull('id_venta')
            ->whereHas('contrato', fn ($q) => $q->where('id_cliente', $idCliente))
            ->sum('monto');

        return round((float) $saldoVentas + (float) $cuotasProgramadas, 2);
    }
}
