<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** FE 2.0 SV: el documento "Evento de retorno" (tipo 18) debe poder crearse en sucursales. */
return new class extends Migration
{
    private const NOMBRE = 'Evento de retorno';

    public function up(): void
    {
        $this->actualizar(function (array $nombres) {
            if (in_array(self::NOMBRE, $nombres, true)) {
                return $nombres;
            }
            $pos = array_search('Nota de débito', $nombres, true);
            array_splice($nombres, $pos === false ? count($nombres) : $pos + 1, 0, [self::NOMBRE]);

            return $nombres;
        });
    }

    public function down(): void
    {
        $this->actualizar(fn (array $nombres) => array_values(array_diff($nombres, [self::NOMBRE])));
    }

    private function actualizar(callable $cambio): void
    {
        if (! Schema::hasTable('pais_configuracion')) {
            return;
        }

        $fila = DB::table('pais_configuracion')->where('pais', 'SV')->where('modulo', 'documentos')->first();
        if (! $fila) {
            return;
        }

        $config = json_decode($fila->configuracion, true) ?: [];
        $config['nombres'] = $cambio($config['nombres'] ?? []);

        DB::table('pais_configuracion')->where('id', $fila->id)->update([
            'configuracion' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
