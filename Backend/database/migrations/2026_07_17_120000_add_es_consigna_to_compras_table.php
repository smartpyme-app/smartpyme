<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->boolean('es_consigna')->default(false)->after('estado');
        });

        DB::table('compras')->where('estado', 'Consigna')->update(['es_consigna' => true]);

        $idsDesdeKardex = DB::table('kardexs')
            ->where('detalle', 'Compra a consigna')
            ->distinct()
            ->pluck('referencia');

        if ($idsDesdeKardex->isNotEmpty()) {
            DB::table('compras')
                ->whereIn('id', $idsDesdeKardex)
                ->where('estado', 'Pagada')
                ->update(['es_consigna' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('es_consigna');
        });
    }
};
