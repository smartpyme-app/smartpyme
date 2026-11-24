<?php

namespace App\Console\Commands;

use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use App\Support\FacturacionElectronica\ClaveComprobanteCr;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Rellena numero_control (20) en documentos FE-CR emitidos que tienen clave pero no consecutivo.
 */
final class BackfillNumeroControlFeCr extends Command
{
    protected $signature = 'fe:cr-backfill-numero-control
                            {--dry-run : Solo cuenta / lista, no escribe}
                            {--id_empresa= : Limitar a una empresa}';

    protected $description = 'FE CR: rellena numero_control desde codigo_generacion/sello_mh cuando falta';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $idEmpresa = $this->option('id_empresa');
        $idEmpresa = $idEmpresa !== null && $idEmpresa !== '' ? (int) $idEmpresa : null;

        $total = 0;
        $total += $this->backfillTabla(Venta::query(), (new Venta)->getTable(), $dry, $idEmpresa);
        $total += $this->backfillTabla(Devolucion::query(), (new Devolucion)->getTable(), $dry, $idEmpresa);
        $total += $this->backfillTabla(Compra::query(), (new Compra)->getTable(), $dry, $idEmpresa);
        $total += $this->backfillTabla(Gasto::query(), (new Gasto)->getTable(), $dry, $idEmpresa);

        $this->info(($dry ? 'Dry-run: ' : 'Actualizados: ').$total.' registro(s).');

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function backfillTabla(Builder $query, string $tabla, bool $dry, ?int $idEmpresa): int
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'numero_control')) {
            return 0;
        }

        $q = $query->where(function (Builder $w): void {
            $w->whereNull('numero_control')->orWhere('numero_control', '');
        })->where(function (Builder $w): void {
            $w->whereNotNull('codigo_generacion')->where('codigo_generacion', '!=', '')
                ->orWhere(function (Builder $w2): void {
                    $w2->whereNotNull('sello_mh')->where('sello_mh', '!=', '');
                });
        });

        if ($idEmpresa !== null && Schema::hasColumn($tabla, 'id_empresa')) {
            $q->where('id_empresa', $idEmpresa);
        }

        $count = 0;
        $q->orderBy('id')->chunkById(200, function ($rows) use (&$count, $dry, $tabla): void {
            foreach ($rows as $row) {
                $clave = trim((string) ($row->codigo_generacion ?: $row->sello_mh ?: ''));
                $consec = ClaveComprobanteCr::consecutivoDesdeClave($clave);
                if ($consec === null) {
                    continue;
                }
                $count++;
                if ($dry) {
                    $this->line("{$tabla}#{$row->id} → {$consec}");
                    continue;
                }
                $row->numero_control = $consec;
                $normalizada = ClaveComprobanteCr::normalizar($clave);
                if (strlen($normalizada) === 50) {
                    $row->codigo_generacion = $normalizada;
                    $row->sello_mh = $normalizada;
                }
                $row->save();
            }
        });

        return $count;
    }
}
