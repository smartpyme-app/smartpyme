<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas de moneda por documento (CR): ventas, compras, egresos (gastos) y devoluciones_venta
 * (usada al emitir Nota de Crédito). Backfill de filas existentes a CRC / TC 1 / equivalentes = nativos.
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
                $table->decimal('crc_equivalent_total', 18, 5)->nullable();
                $table->decimal('crc_equivalent_iva', 18, 5)->nullable();
            });
        }

        foreach (self::TABLAS as $tabla) {
            DB::table($tabla)->whereNull('crc_equivalent_total')->update([
                'currency_code' => 'CRC',
                'exchange_rate' => 1,
                'exchange_rate_date' => DB::raw('COALESCE(DATE(fecha), DATE(created_at))'),
                'crc_equivalent_total' => DB::raw('total'),
                'crc_equivalent_iva' => DB::raw('iva'),
            ]);
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
                    'crc_equivalent_total',
                    'crc_equivalent_iva',
                ]);
            });
        }
    }
};
