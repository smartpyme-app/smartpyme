<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pais_configuracion')) {
            return;
        }

        $existe = DB::table('pais_configuracion')
            ->where('pais', 'SV')
            ->where('modulo', 'facturacion_electronica')
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('pais_configuracion')->insert([
            'pais' => 'SV',
            'modulo' => 'facturacion_electronica',
            'configuracion' => json_encode([
                'url_firmador' => 'https://facturadtesv.com:8443/firmardocumento/',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('pais_configuracion')) {
            return;
        }

        DB::table('pais_configuracion')
            ->where('pais', 'SV')
            ->where('modulo', 'facturacion_electronica')
            ->delete();
    }
};
