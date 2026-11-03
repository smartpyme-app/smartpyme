<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ResetFidelizacionEmpresaCommand extends Command
{
    protected $signature = 'fidelizacion:reset-empresa
                            {empresa : ID de la empresa (padre o sucursal; incluye toda la licencia)}
                            {--dry-run : Mostrar conteos sin modificar datos}
                            {--force : Ejecutar sin confirmación interactiva}';

    protected $description = 'Pone en cero los datos de fidelización de una empresa/licencia (no elimina ventas ni clientes)';

    public function handle(): int
    {
        $empresaId = (int) $this->argument('empresa');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $empresa = Empresa::find($empresaId);
        if (!$empresa) {
            $this->error("No existe la empresa con ID {$empresaId}.");
            return Command::FAILURE;
        }

        $empresaIds = array_values(array_unique(array_map('intval', $empresa->getEmpresasLicenciaIds())));
        if ($empresaIds === []) {
            $empresaIds = [$empresaId];
        }

        $conteos = $this->conteos($empresaIds);

        $this->info("Empresa: {$empresa->nombre} (ID {$empresaId})");
        $this->line('Alcance id_empresa en licencia: ' . implode(', ', $empresaIds));
        $this->line('Base de datos: ' . DB::connection()->getDatabaseName());
        $this->newLine();
        $this->table(
            ['Recurso', 'Registros afectados'],
            [
                ['consumo_puntos (FIFO)', $conteos['consumo_puntos']],
                ['transacciones_puntos', $conteos['transacciones_puntos']],
                ['puntos_cliente', $conteos['puntos_cliente']],
                ['ventas (solo campos de puntos)', $conteos['ventas_campos_puntos']],
                ['cliente_fidelizacion_snapshot', $conteos['snapshot']],
                ['cliente_actividad_reciente (puntos)', $conteos['actividad_puntos']],
            ]
        );

        $this->newLine();
        $this->line('Las ventas <fg=green>no se eliminan</>. Solo se limpian puntos_ganados, puntos_canjeados y descuento_puntos.');
        $this->warn('Si alguna venta tuvo descuento por canje de puntos, al limpiar descuento_puntos el monto final mostrado puede cambiar; el total de la factura no se borra.');
        $this->comment('Ejecuta este comando en el mismo backend/.env que usa tu API (Frontend environment.API_URL).');

        if ($conteos['total'] === 0) {
            $this->info('No hay datos de fidelización que resetear para esta licencia.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY-RUN: no se aplicaron cambios. Quita --dry-run y confirma (o usa --force) para aplicar.');
            return Command::SUCCESS;
        }

        if (!$force && !$this->confirm('¿Continuar con el reset de fidelización?', false)) {
            $this->info('Operación cancelada.');
            return Command::SUCCESS;
        }

        DB::transaction(function () use ($empresaIds, $empresaId, $conteos) {
            $txIds = DB::table('transacciones_puntos')
                ->whereIn('id_empresa', $empresaIds)
                ->pluck('id');

            if ($txIds->isNotEmpty()) {
                DB::table('consumo_puntos')
                    ->where(function ($q) use ($txIds) {
                        $q->whereIn('id_canje_tx', $txIds)
                            ->orWhereIn('id_ganancia_tx', $txIds);
                    })
                    ->delete();

                DB::table('cliente_actividad_reciente')
                    ->whereIn('tipo_actividad', ['puntos_ganados', 'puntos_canjeados'])
                    ->whereIn('id_referencia', $txIds)
                    ->delete();

                DB::table('transacciones_puntos')
                    ->whereIn('id_empresa', $empresaIds)
                    ->delete();
            }

            DB::table('puntos_cliente')->whereIn('id_empresa', $empresaIds)->delete();

            DB::table('ventas')
                ->whereIn('id_empresa', $empresaIds)
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('puntos_ganados')->where('puntos_ganados', '>', 0);
                    })->orWhere(function ($q2) {
                        $q2->whereNotNull('puntos_canjeados')->where('puntos_canjeados', '>', 0);
                    })->orWhere(function ($q2) {
                        $q2->whereNotNull('descuento_puntos')->where('descuento_puntos', '>', 0);
                    });
                })
                ->update([
                    'puntos_ganados' => null,
                    'puntos_canjeados' => null,
                    'descuento_puntos' => null,
                ]);

            if (Schema::hasColumn('cliente_fidelizacion_snapshot', 'id_empresa')) {
                DB::table('cliente_fidelizacion_snapshot')
                    ->whereIn('id_empresa', $empresaIds)
                    ->delete();
            } else {
                $clienteIds = DB::table('clientes')
                    ->whereIn('id_empresa', $empresaIds)
                    ->pluck('id');
                if ($clienteIds->isNotEmpty()) {
                    DB::table('cliente_fidelizacion_snapshot')
                        ->whereIn('id_cliente', $clienteIds)
                        ->delete();
                }
            }

            Log::warning('fidelizacion:reset-empresa ejecutado', [
                'id_empresa_solicitada' => $empresaId,
                'id_empresas_licencia' => $empresaIds,
                'conteos' => $conteos,
            ]);
        });

        $this->newLine();
        $this->info('✅ Fidelización reseteada. Saldos e historial de puntos en cero para la licencia.');
        $this->line('Tipos de cliente e inscripción de clientes se mantienen; solo puntos y transacciones.');

        return Command::SUCCESS;
    }

    /**
     * @param  int[]  $empresaIds
     * @return array<string, int>
     */
    private function conteos(array $empresaIds): array
    {
        $txIds = DB::table('transacciones_puntos')
            ->whereIn('id_empresa', $empresaIds)
            ->pluck('id');

        $consumo = 0;
        if ($txIds->isNotEmpty()) {
            $consumo = (int) DB::table('consumo_puntos')
                ->where(function ($q) use ($txIds) {
                    $q->whereIn('id_canje_tx', $txIds)
                        ->orWhereIn('id_ganancia_tx', $txIds);
                })
                ->count();
        }

        $transacciones = (int) DB::table('transacciones_puntos')
            ->whereIn('id_empresa', $empresaIds)
            ->count();

        $puntosCliente = (int) DB::table('puntos_cliente')
            ->whereIn('id_empresa', $empresaIds)
            ->count();

        $ventasCampos = (int) DB::table('ventas')
            ->whereIn('id_empresa', $empresaIds)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('puntos_ganados')->where('puntos_ganados', '>', 0);
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('puntos_canjeados')->where('puntos_canjeados', '>', 0);
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('descuento_puntos')->where('descuento_puntos', '>', 0);
                });
            })
            ->count();

        $snapshot = $this->conteoSnapshot($empresaIds);

        $actividadPuntos = 0;
        if ($txIds->isNotEmpty()) {
            $actividadPuntos = (int) DB::table('cliente_actividad_reciente')
                ->whereIn('tipo_actividad', ['puntos_ganados', 'puntos_canjeados'])
                ->whereIn('id_referencia', $txIds)
                ->count();
        }

        return [
            'consumo_puntos' => $consumo,
            'transacciones_puntos' => $transacciones,
            'puntos_cliente' => $puntosCliente,
            'ventas_campos_puntos' => $ventasCampos,
            'snapshot' => $snapshot,
            'actividad_puntos' => $actividadPuntos,
            'total' => $consumo + $transacciones + $puntosCliente + $ventasCampos + $snapshot + $actividadPuntos,
        ];
    }

    /** @param  int[]  $empresaIds */
    private function conteoSnapshot(array $empresaIds): int
    {
        if (Schema::hasColumn('cliente_fidelizacion_snapshot', 'id_empresa')) {
            return (int) DB::table('cliente_fidelizacion_snapshot')
                ->whereIn('id_empresa', $empresaIds)
                ->count();
        }

        $clienteIds = DB::table('clientes')->whereIn('id_empresa', $empresaIds)->pluck('id');
        if ($clienteIds->isEmpty()) {
            return 0;
        }

        return (int) DB::table('cliente_fidelizacion_snapshot')
            ->whereIn('id_cliente', $clienteIds)
            ->count();
    }
}
