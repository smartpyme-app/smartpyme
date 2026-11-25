<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** FE CR: el documento "Recibo electrónico" (correlativo REP tipo 10) debe aparecer en el select de documentos. */
return new class extends Migration
{
    private const NOMBRE = 'Recibo electrónico';

    public function up(): void
    {
        $this->actualizar(function (array $nombres) {
            if (in_array(self::NOMBRE, $nombres, true)) {
                return $nombres;
            }
            $pos = array_search('Abono de Venta', $nombres, true);
            if ($pos === false) {
                $nombres[] = self::NOMBRE;
            } else {
                array_splice($nombres, $pos, 0, [self::NOMBRE]);
            }

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

        $fila = DB::table('pais_configuracion')->where('pais', 'CR')->where('modulo', 'documentos')->first();
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
