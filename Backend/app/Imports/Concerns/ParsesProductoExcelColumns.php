<?php

namespace App\Imports\Concerns;

use App\Models\Admin\Impuesto;
use App\Models\Inventario\Producto;
use Illuminate\Support\Facades\Schema;

trait ParsesProductoExcelColumns
{
    /**
     * @param  mixed  $value
     * @return array{tipo: string, valor: float|string}|null
     */
    protected function parseImpuestoExcelValue($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ['tipo' => 'porcentaje', 'valor' => (float) $value];
        }

        $s = trim((string) $value);
        $normalized = str_replace(['%', ' '], '', $s);
        $normalized = str_replace(',', '.', $normalized);
        if (is_numeric($normalized)) {
            return ['tipo' => 'porcentaje', 'valor' => (float) $normalized];
        }

        return ['tipo' => 'nombre', 'valor' => $s];
    }

    /**
     * @param  mixed  $value
     */
    protected function parseMarcaExcelValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    /**
     * La plantilla oficial no incluye subcategoría; la columna es opcional.
     *
     * @param  mixed  $value
     */
    protected function parseSubcategoriaExcelValue($value): ?string
    {
        return $this->parseMarcaExcelValue($value);
    }

    /**
     * Aplica impuesto solo si la celda trae valor. Vacío = no error y no borra impuestos existentes.
     *
     * @param  mixed  $rawValue
     */
    protected function applyImpuestoExcelToProducto(Producto $producto, $rawValue, int $idEmpresa): void
    {
        $parsed = $this->parseImpuestoExcelValue($rawValue);
        if ($parsed === null) {
            return;
        }

        $impuesto = null;
        if ($parsed['tipo'] === 'nombre') {
            $impuesto = Impuesto::withoutGlobalScopes()
                ->where('id_empresa', $idEmpresa)
                ->where('aplica_ventas', true)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $parsed['valor'])])
                ->first();
        } else {
            $pct = round((float) $parsed['valor'], 2);
            $impuesto = Impuesto::withoutGlobalScopes()
                ->where('id_empresa', $idEmpresa)
                ->where('aplica_ventas', true)
                ->where('porcentaje', $pct)
                ->first();

            // Sin catálogo coincidente: solo deja el % legacy (no falla la fila).
            if (! $impuesto) {
                $producto->porcentaje_impuesto = $pct;
                $producto->save();

                return;
            }
        }

        if (! $impuesto) {
            return;
        }

        if (Schema::hasTable('producto_impuestos')) {
            $producto->impuestos()->sync([$impuesto->id]);
        }
        $producto->porcentaje_impuesto = round((float) $impuesto->porcentaje, 2);
        $producto->save();
    }
}
