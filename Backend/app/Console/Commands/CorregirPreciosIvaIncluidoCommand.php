<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** SPT-629: precio de catálogo cargado con IVA incluido como si fuera precio sin IVA. */
class CorregirPreciosIvaIncluidoCommand extends Command
{
    /** @var list<int> IDs en prod (397 y 427 son la misma razón social en registros distintos). */
    //public const EMPRESAS_SPT629 = [397, 427, 398, 396, 432, 543, 657];
    public const EMPRESAS_SPT629 = [396, 397, 398, 432, 657, 690];

    protected $signature = 'productos:corregir-precios-iva-incluido
                            {--empresa=* : IDs de empresa (default: alcance SPT-629)}
                            {--dry-run : Mostrar cambios sin escribir en BD}
                            {--force : Ejecutar sin confirmación interactiva}';

    protected $description = 'Quita IVA del precio de catálogo cuando fue cargado como total final (productos y servicios)';

    public function handle(): int
    {
        $idsEmpresa = array_map('intval', $this->option('empresa'));
        if ($idsEmpresa === [] || $idsEmpresa === [0]) {
            $idsEmpresa = self::EMPRESAS_SPT629;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Modo dry-run: no se escribirán cambios.');
        }

        $empresas = Empresa::withoutGlobalScopes()
            ->whereIn('id', $idsEmpresa)
            ->get(['id', 'nombre', 'iva', 'cobra_iva']);

        $faltantes = array_diff($idsEmpresa, $empresas->pluck('id')->all());
        if ($faltantes !== []) {
            $this->error('Empresas no encontradas: ' . implode(', ', $faltantes));

            return 1;
        }

        $this->info('Empresas: ' . $empresas->pluck('nombre', 'id')->map(
            fn ($nombre, $id) => "#{$id} {$nombre}"
        )->implode(' | '));

        $ivaPorEmpresa = $empresas->mapWithKeys(fn (Empresa $e) => [$e->id => (float) ($e->iva ?? 0)]);

        $productos = Producto::withoutGlobalScopes()
            ->whereIn('id_empresa', $idsEmpresa)
            ->whereIn('tipo', ['Producto', 'Compuesto', 'Servicio'])
            ->whereNull('deleted_at')
            ->orderBy('id_empresa')
            ->orderBy('id')
            ->get(['id', 'id_empresa', 'codigo', 'nombre', 'tipo', 'precio', 'precio_sin_iva', 'precio_con_iva', 'porcentaje_impuesto']);

        if ($productos->isEmpty()) {
            $this->warn('No hay productos/servicios en las empresas indicadas.');

            return 0;
        }

        $filas = [];
        foreach ($productos as $producto) {
            $idEmpresa = (int) $producto->id_empresa;
            $ivaPct = self::porcentajeIvaEfectivo(
                $producto->porcentaje_impuesto !== null ? (float) $producto->porcentaje_impuesto : null,
                $ivaPorEmpresa[$idEmpresa] ?? 0.0
            );
            if ($ivaPct <= 0) {
                continue;
            }

            $precio = (float) $producto->precio;
            $sinIva = $producto->precio_sin_iva !== null ? (float) $producto->precio_sin_iva : null;
            $conIva = $producto->precio_con_iva !== null ? (float) $producto->precio_con_iva : null;

            if (! self::necesitaCorreccion($precio, $sinIva, $conIva, $ivaPct)) {
                continue;
            }

            $corregido = self::preciosCorregidos($precio, $ivaPct);
            $filas[] = [
                'producto' => $producto,
                'iva_pct' => $ivaPct,
                'corregido' => $corregido,
            ];
        }

        if ($filas === []) {
            $this->info('Ningún ítem requiere corrección (o ya fueron corregidos).');

            return 0;
        }

        $this->info('Ítems a corregir: ' . count($filas));
        $this->table(
            ['Empresa', 'ID', 'Código', 'Nombre', 'IVA%', 'Precio actual', '→ sin IVA', '→ con IVA'],
            array_map(function (array $fila) {
                $p = $fila['producto'];

                return [
                    $p->id_empresa,
                    $p->id,
                    $p->codigo,
                    mb_strimwidth((string) $p->nombre, 0, 40, '…'),
                    $fila['iva_pct'],
                    number_format((float) $p->precio, 2, '.', ''),
                    number_format($fila['corregido']['precio'], 2, '.', ''),
                    number_format($fila['corregido']['precio_con_iva'], 2, '.', ''),
                ];
            }, array_slice($filas, 0, 50))
        );
        if (count($filas) > 50) {
            $this->line('… y ' . (count($filas) - 50) . ' más (solo se listan los primeros 50).');
        }

        if ($dryRun) {
            $this->comment('Ejecutar una sola vez en producción; validar esta salida antes de correr sin --dry-run.');

            return 0;
        }

        if (! $this->option('force') && ! $this->confirm('¿Aplicar estos cambios en base de datos?', false)) {
            $this->info('Operación cancelada.');

            return 0;
        }

        $actualizados = 0;
        DB::transaction(function () use ($filas, &$actualizados) {
            foreach ($filas as $fila) {
                /** @var Producto $producto */
                $producto = $fila['producto'];
                $patch = $fila['corregido'];
                Producto::withoutGlobalScopes()
                    ->where('id', $producto->id)
                    ->update($patch);
                $actualizados++;
            }
        });

        $this->info("Actualizados: {$actualizados} producto(s)/servicio(s).");

        return 0;
    }

    public static function porcentajeIvaEfectivo(?float $porcentajeProducto, float $ivaEmpresa): float
    {
        $producto = (float) ($porcentajeProducto ?? 0);

        return $producto > 0 ? $producto : $ivaEmpresa;
    }

    /**
     * Detecta catálogo donde el precio guardado es el total con IVA (redondeo típico de salón).
     * Idempotente: tras corregir, precio deja de ser mayor que la base sin IVA inferida.
     */
    public static function necesitaCorreccion(
        float $precio,
        ?float $precioSinIva,
        ?float $precioConIva,
        float $ivaPct
    ): bool {
        if ($precio <= 0 || $ivaPct <= 0) {
            return false;
        }

        $baseSinIva = round($precio / (1 + ($ivaPct / 100)), 2);
        if ($precio <= $baseSinIva + 0.01) {
            return false;
        }

        if ($precioSinIva !== null && abs($precioSinIva - $precio) >= 0.02) {
            return false;
        }

        if ($precioConIva !== null) {
            $conCalculado = round($precio * (1 + ($ivaPct / 100)), 2);
            if (abs($precioConIva - $conCalculado) >= 0.02) {
                return false;
            }
        }

        return true;
    }

    /** @return array{precio: float, precio_sin_iva: float, precio_con_iva: float} */
    public static function preciosCorregidos(float $precioConIvaIncluidoMalCargado, float $ivaPct): array
    {
        $totalDeseado = round($precioConIvaIncluidoMalCargado, 2);
        $sinIva = round($totalDeseado / (1 + ($ivaPct / 100)), 2);

        return [
            'precio' => $sinIva,
            'precio_sin_iva' => $sinIva,
            'precio_con_iva' => $totalDeseado,
        ];
    }
}
