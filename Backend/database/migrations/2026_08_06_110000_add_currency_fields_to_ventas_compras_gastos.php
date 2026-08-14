<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas de moneda por documento: ventas, compras, egresos (gastos) y devoluciones_venta.
 * Sin backfill masivo (BD grandes): defaults en columnas; equivalent_* quedan null
 * hasta que el documento se vuelva a guardar o se backfillée por lotes aparte.
 * equivalent_* = monto en moneda funcional del país (CRC, HNL, USD, etc.).
 *
 * Spec: Docs/superpowers/specs/2026-08-03-cr-multimoneda-design.md §7.2
 */
return new class extends Migration
{
    private const TABLAS = ['ventas', 'compras', 'egresos', 'devoluciones_venta'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->char('currency_code', 3)->default('CRC');
                $table->decimal('exchange_rate', 18, 5)->default(1);
                $table->date('exchange_rate_date')->nullable();
                $table->decimal('equivalent_total', 18, 5)->nullable();
                $table->decimal('equivalent_iva', 18, 5)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn([
                    'currency_code',
                    'exchange_rate',
                    'exchange_rate_date',
                    'equivalent_total',
                    'equivalent_iva',
                ]);
            });
        }
    }
};
