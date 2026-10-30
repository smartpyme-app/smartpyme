<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Montos en colones y totales altos (p. ej. precio_con_iva > 999 999). */
return new class extends Migration
{
    private const P = 16;

    private const S = 6;

    public function up(): void
    {
        $this->widen('detalles_venta', [
            'precio',
            'precio_sin_iva',
            'precio_con_iva',
            'costo',
            'descuento',
            'sub_total',
            'total',
            'gravada',
            'iva',
        ]);

        $this->widen('productos', ['precio_sin_iva', 'precio_con_iva']);
    }

    public function down(): void
    {
        // Revertir solo columnas añadidas con precisión conocida; el resto queda en 16,6.
    }

    /** @param list<string> $columns */
    private function widen(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $nullable = ['precio_sin_iva', 'precio_con_iva', 'costo', 'descuento', 'sub_total', 'total', 'gravada', 'iva'];

        Schema::table($table, function (Blueprint $blueprint) use ($table, $columns, $nullable) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $col = $blueprint->decimal($column, self::P, self::S);
                if (in_array($column, $nullable, true)) {
                    $col->nullable();
                }
                $col->change();
            }
        });
    }
};
