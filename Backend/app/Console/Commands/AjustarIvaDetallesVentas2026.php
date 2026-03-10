<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use Illuminate\Console\Command;

class AjustarIvaDetallesVentas2026 extends Command
{
    protected $signature = 'ventas:ajustar-iva-detalles-2026
                            {--dry-run : Solo mostrar qué se actualizaría, sin ejecutar}
                            {--id_venta= : Limitar a una venta (opcional)}';

    protected $description = 'Calcula y rellena el IVA en detalles de venta gravados del año 2026 donde iva es cero o nulo';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $idVentaFiltro = $this->option('id_venta');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se aplicarán cambios.');
        }

        $this->info('Buscando detalles de ventas 2026 con IVA en cero y gravados...');

        $query = Detalle::query()
            ->join('ventas', 'ventas.id', '=', 'detalles_venta.id_venta')
            ->whereYear('ventas.fecha', 2026)
            ->where(function ($q) {
                $q->whereNull('detalles_venta.iva')
                    ->orWhere('detalles_venta.iva', 0);
            })
            ->where(function ($q) {
                $q->where('detalles_venta.tipo_gravado', 'gravada')
                    ->orWhere('detalles_venta.gravada', '>', 0);
            })
            ->select('detalles_venta.*');

        if ($idVentaFiltro) {
            $query->where('detalles_venta.id_venta', $idVentaFiltro);
        }

        $detalles = $query->get();

        if ($detalles->isEmpty()) {
            $this->info('No se encontraron detalles a ajustar.');
            return 0;
        }

        $this->info('Detalles a procesar: ' . $detalles->count());

        $idVentas = $detalles->pluck('id_venta')->unique()->values();
        $ventasEmpresa = Venta::whereIn('id', $idVentas)->pluck('id_empresa', 'id');
        $empresasIva = Empresa::whereIn('id', $ventasEmpresa->values()->unique()->filter())->pluck('iva', 'id');

        $actualizados = 0;
        $errores = 0;

        foreach ($detalles as $detalle) {
            $idEmpresa = $ventasEmpresa[$detalle->id_venta] ?? null;
            $porcentaje = $detalle->porcentaje_impuesto !== null && $detalle->porcentaje_impuesto != ''
                ? (float) $detalle->porcentaje_impuesto
                : (float) ($empresasIva[$idEmpresa] ?? 0);

            if ($porcentaje <= 0) {
                $this->warn("Detalle id={$detalle->id} (venta {$detalle->id_venta}): sin porcentaje de impuesto, se omite.");
                $errores++;
                continue;
            }

            $gravada = (float) ($detalle->gravada ?? 0);
            $total = (float) ($detalle->total ?? 0);

            if ($gravada > 0) {
                $ivaCalculado = round($gravada * ($porcentaje / 100), 4);
            } elseif ($total > 0) {
                $ivaCalculado = round($total * ($porcentaje / 100) / (1 + $porcentaje / 100), 4);
                $gravadaCalculada = round($total - $ivaCalculado, 4);
            } else {
                $this->warn("Detalle id={$detalle->id}: gravada y total en 0, se omite.");
                $errores++;
                continue;
            }

            if ($ivaCalculado <= 0) {
                continue;
            }

            if (!$dryRun) {
                try {
                    $update = ['iva' => $ivaCalculado];
                    if ($gravada <= 0 && $total > 0 && isset($gravadaCalculada)) {
                        $update['gravada'] = $gravadaCalculada;
                    }
                    Detalle::where('id', $detalle->id)->update($update);
                    $actualizados++;
                } catch (\Exception $e) {
                    $this->error("Error detalle id={$detalle->id}: " . $e->getMessage());
                    $errores++;
                }
            } else {
                $actualizados++;
            }
        }

        if ($dryRun) {
            $this->info("Dry-run: se habrían actualizado {$actualizados} detalle(s). Errores/omitidos: {$errores}.");
        } else {
            $this->info("Listo. Detalles actualizados: {$actualizados}. Errores/omitidos: {$errores}.");
        }

        if ($actualizados > 0 && !$dryRun) {
            $this->info('Puede ejecutar una recalculación de totales por venta si lo requiere (suma de iva en cabecera).');
        }

        return 0;
    }
}
