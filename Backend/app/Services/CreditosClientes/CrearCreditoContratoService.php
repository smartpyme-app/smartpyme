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
        $saldoUsado = $this->saldoUsado((int) $cliente->id);
        $limite = $cliente->limite_credito !== null ? (float) $cliente->limite_credito : null;

        if (!CupoCredito::cabe($limite, $saldoUsado, $monto)) {
            throw ValidationException::withMessages([
                'monto' => 'El saldo más este monto supera el límite de crédito del cliente.',
            ]);
        }

        $plan = PlanCuotasIguales::generar(
            $monto,
            (int) $data['n_cuotas'],
            $data['fecha_inicio']
        );

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

    public function saldoUsado(int $idCliente): float
    {
        $ventasPendientes = Venta::where('id_cliente', $idCliente)
            ->where('estado', 'Pendiente')
            ->where(function ($q) {
                $q->where('cotizacion', 0)->orWhereNull('cotizacion');
            })
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
            ->whereHas('contrato', fn ($q) => $q->where('id_cliente', $idCliente))
            ->sum('monto');

        return round((float) $saldoVentas + (float) $cuotasProgramadas, 2);
    }
}
