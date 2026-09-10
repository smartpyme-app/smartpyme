<?php

namespace Database\Seeders;

use App\Models\Admin\Empresa;
use App\Models\PrestamosEmpresa\PrestamoCuota;
use App\Models\PrestamosEmpresa\PrestamoEmpresa;
use App\Models\PrestamosEmpresa\PrestamoPago;
use App\Models\User;
use App\Services\PrestamosEmpresa\PlanAmortizacion;
use Illuminate\Database\Seeder;

class PrestamosEmpresaDatosSeeder extends Seeder
{
    public const CONCEPTO = '[demo] Datos de ejemplo';

    public function run(): void
    {
        $empresaIds = User::query()->whereNotNull('id_empresa')->distinct()->pluck('id_empresa');
        if ($empresaIds->isEmpty()) {
            $empresaIds = Empresa::query()->limit(20)->pluck('id');
        }

        $creados = 0;
        foreach ($empresaIds as $idEmpresa) {
            $usuarioId = User::query()->where('id_empresa', $idEmpresa)->value('id');
            if (PrestamoEmpresa::withoutGlobalScopes()
                ->where('id_empresa', $idEmpresa)
                ->where('concepto', self::CONCEPTO)
                ->exists()) {
                continue;
            }

            $this->crear($idEmpresa, $usuarioId, [
                'tipo_acreedor' => 'institucion',
                'acreedor' => 'Banco Agrícola',
                'monto' => 12000,
                'n_cuotas' => 12,
                'genera_interes' => true,
                'tasa_interes' => 12,
                'fecha_desembolso' => now()->subMonths(3)->toDateString(),
                'fecha_primera_cuota' => now()->subMonths(2)->toDateString(),
                'cuotas_pagadas' => 2,
            ]);
            $this->crear($idEmpresa, $usuarioId, [
                'tipo_acreedor' => 'persona',
                'acreedor' => 'Juan Pérez',
                'monto' => 3000,
                'n_cuotas' => 6,
                'genera_interes' => false,
                'tasa_interes' => 0,
                'fecha_desembolso' => now()->subMonth()->toDateString(),
                'fecha_primera_cuota' => now()->toDateString(),
                'cuotas_pagadas' => 0,
            ]);
            $this->crear($idEmpresa, $usuarioId, [
                'tipo_acreedor' => 'institucion',
                'acreedor' => 'Banco Cuscatlán',
                'monto' => 2400,
                'n_cuotas' => 6,
                'genera_interes' => false,
                'tasa_interes' => 0,
                'fecha_desembolso' => now()->subMonths(8)->toDateString(),
                'fecha_primera_cuota' => now()->subMonths(7)->toDateString(),
                'cuotas_pagadas' => 6,
            ]);
            $creados++;
        }

        $this->command?->info("Datos demo de préstamos en {$creados} empresas (3 préstamos c/u).");
    }

    private function crear(int $idEmpresa, ?int $usuarioId, array $data): void
    {
        $plan = PlanAmortizacion::generar(
            $data['genera_interes'],
            (float) $data['monto'],
            (float) $data['tasa_interes'],
            (int) $data['n_cuotas'],
            $data['fecha_primera_cuota'],
            'mensual'
        );

        $prestamo = PrestamoEmpresa::withoutGlobalScopes()->create([
            'id_empresa' => $idEmpresa,
            'id_usuario' => $usuarioId,
            'tipo_acreedor' => $data['tipo_acreedor'],
            'acreedor' => $data['acreedor'],
            'concepto' => self::CONCEPTO,
            'historico' => true,
            'monto' => $data['monto'],
            'saldo' => $data['monto'],
            'genera_interes' => $data['genera_interes'],
            'tasa_interes' => $data['tasa_interes'],
            'n_cuotas' => $data['n_cuotas'],
            'frecuencia' => 'mensual',
            'fecha_desembolso' => $data['fecha_desembolso'],
            'fecha_primera_cuota' => $data['fecha_primera_cuota'],
            'generar_asiento_desembolso' => false,
            'clasificacion' => PlanAmortizacion::clasificacion((int) $data['n_cuotas'], 'mensual'),
            'estado' => 'activo',
        ]);

        $cuotas = [];
        foreach ($plan as $fila) {
            $cuotas[] = PrestamoCuota::create([
                'id_prestamo' => $prestamo->id,
                'numero' => $fila['numero'],
                'fecha_vencimiento' => $fila['fecha_vencimiento'],
                'capital' => $fila['capital'],
                'interes' => $fila['interes'],
                'total' => $fila['total'],
                'estado' => 'pendiente',
            ]);
        }

        $pagar = (int) $data['cuotas_pagadas'];
        if ($pagar < 1) {
            return;
        }

        $elegidas = array_slice($cuotas, 0, $pagar);
        $capitalPago = round(array_sum(array_map(fn ($c) => (float) $c->capital, $elegidas)), 2);
        $interesPago = round(array_sum(array_map(fn ($c) => (float) $c->interes, $elegidas)), 2);

        $pago = PrestamoPago::create([
            'id_prestamo' => $prestamo->id,
            'fecha' => now()->subDays(5)->toDateString(),
            'monto' => round($capitalPago + $interesPago, 2),
            'capital' => $capitalPago,
            'interes' => $interesPago,
            'metodo' => 'transferencia',
            'id_usuario' => $usuarioId,
        ]);

        foreach ($elegidas as $cuota) {
            $cuota->estado = 'pagada';
            $cuota->id_pago = $pago->id;
            $cuota->save();
        }

        $saldo = round((float) $prestamo->monto - $capitalPago, 2);
        $prestamo->saldo = max(0, $saldo);
        $prestamo->estado = $prestamo->saldo <= 0 ? 'pagado' : 'activo';
        $prestamo->save();
    }
}
