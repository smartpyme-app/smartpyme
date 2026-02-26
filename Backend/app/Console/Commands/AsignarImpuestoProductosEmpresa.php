<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AsignarImpuestoProductosEmpresa extends Command
{
    protected $signature = 'productos:asignar-impuesto';

    protected $description = 'A todos los productos sin impuesto les asigna el IVA de su empresa (por id_empresa)';

    public function handle()
    {
        // Solo IDs de empresas que tienen productos sin impuesto (poca memoria)
        $idsEmpresa = DB::table('productos')
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->where(function ($q) {
                $q->whereNull('porcentaje_impuesto')->orWhere('porcentaje_impuesto', '');
            })
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('id_empresa');

        if ($idsEmpresa->isEmpty()) {
            $this->info('No hay productos sin impuesto asignado.');
            return 0;
        }

        // Solo iva de esas empresas (poca memoria)
        $empresasIva = DB::table('empresas')
            ->whereIn('id', $idsEmpresa)
            ->pluck('iva', 'id');

        $actualizados = 0;
        foreach ($empresasIva as $idEmpresa => $iva) {
            $iva = (float) ($iva ?? 0);
            $count = DB::table('productos')
                ->where('id_empresa', $idEmpresa)
                ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
                ->where(function ($q) {
                    $q->whereNull('porcentaje_impuesto')->orWhere('porcentaje_impuesto', '');
                })
                ->whereNull('deleted_at')
                ->update(['porcentaje_impuesto' => $iva]);
            $actualizados += $count;
        }

        $this->info("Se asignó el impuesto de la empresa a {$actualizados} producto(s) que no tenían impuesto.");
        return 0;
    }
}
