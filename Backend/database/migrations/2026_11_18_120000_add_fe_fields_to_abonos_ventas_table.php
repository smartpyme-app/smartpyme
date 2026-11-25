<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** FE CR: Recibo electrónico de pago (tipo 10) persistido en el abono de venta. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonos_ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('abonos_ventas', 'tipo_dte')) {
                $table->string('tipo_dte', 2)->nullable()->after('estado');
            }
            if (! Schema::hasColumn('abonos_ventas', 'codigo_generacion')) {
                $table->string('codigo_generacion', 50)->nullable()->after('tipo_dte');
            }
            if (! Schema::hasColumn('abonos_ventas', 'sello_mh')) {
                $table->string('sello_mh', 50)->nullable()->after('codigo_generacion');
            }
            if (! Schema::hasColumn('abonos_ventas', 'dte')) {
                $table->longText('dte')->nullable()->after('sello_mh');
            }
        });
    }

    public function down(): void
    {
        Schema::table('abonos_ventas', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['tipo_dte', 'codigo_generacion', 'sello_mh', 'dte'],
                fn (string $c) => Schema::hasColumn('abonos_ventas', $c)
            ));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
