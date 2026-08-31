<?php

use App\Support\FacturacionElectronica\Cat020Pais;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->remapTabla('clientes');
        $this->remapTabla('proveedores');
        $this->sincronizarPaises();
    }

    public function down(): void
    {
        // ponytail: no hay vuelta segura al CAT-020 numérico; Hacienda ya no lo acepta
    }

    private function remapTabla(string $tabla): void
    {
        if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, 'cod_pais')) {
            return;
        }
        $tienePais = Schema::hasColumn($tabla, 'pais');
        $cols = $tienePais ? ['id', 'cod_pais', 'pais'] : ['id', 'cod_pais'];

        DB::table($tabla)->select($cols)->orderBy('id')->chunkById(500, function ($rows) use ($tabla, $tienePais) {
            foreach ($rows as $row) {
                $resuelto = Cat020Pais::resolver($row->cod_pais, $tienePais ? $row->pais : null);
                if ($resuelto['cod'] === null) {
                    continue;
                }
                $update = [];
                if ((string) $row->cod_pais !== $resuelto['cod']) {
                    $update['cod_pais'] = $resuelto['cod'];
                }
                if ($tienePais && $resuelto['nombre'] !== null && (string) $row->pais !== $resuelto['nombre']) {
                    $update['pais'] = $resuelto['nombre'];
                }
                if ($update !== []) {
                    DB::table($tabla)->where('id', $row->id)->update($update);
                }
            }
        });
    }

    private function sincronizarPaises(): void
    {
        if (!Schema::hasTable('paises')) {
            return;
        }

        $catalogo = Cat020Pais::catalogo();
        $existentes = DB::table('paises')->get();
        $porCod = [];
        $porIsoNombre = [];
        foreach ($existentes as $pais) {
            $porCod[(string) $pais->cod] = $pais;
            $iso = Cat020Pais::isoDesdeNombre($pais->nombre);
            if ($iso !== null && !isset($porIsoNombre[$iso])) {
                $porIsoNombre[$iso] = $pais;
            }
        }

        $idsUsados = [];
        $tieneTimestamps = Schema::hasColumn('paises', 'created_at');

        foreach ($catalogo as $iso => $nombre) {
            $row = $porCod[$iso] ?? $porIsoNombre[$iso] ?? null;
            if ($row) {
                $cambio = [];
                if ((string) $row->cod !== $iso) {
                    $cambio['cod'] = $iso;
                }
                if ((string) $row->nombre !== $nombre) {
                    $cambio['nombre'] = $nombre;
                }
                if ($cambio !== []) {
                    DB::table('paises')->where('id', $row->id)->update($cambio);
                }
                $idsUsados[$row->id] = true;
                continue;
            }

            $insert = ['cod' => $iso, 'nombre' => $nombre];
            if ($tieneTimestamps) {
                $ahora = now();
                $insert['created_at'] = $ahora;
                $insert['updated_at'] = $ahora;
            }
            DB::table('paises')->insert($insert);
        }

        $idsObsoletos = [];
        foreach ($existentes as $pais) {
            if (isset($idsUsados[$pais->id])) {
                continue;
            }
            if (!isset($catalogo[(string) $pais->cod])) {
                $idsObsoletos[] = $pais->id;
            }
        }
        if ($idsObsoletos === []) {
            return;
        }

        if (Schema::hasTable('estados_paises') && Schema::hasColumn('estados_paises', 'pais_id')) {
            $isoPorPaisId = DB::table('paises')->whereIn('cod', array_keys($catalogo))->pluck('id', 'cod');
            $obsoletos = $existentes->whereIn('id', $idsObsoletos);
            foreach ($obsoletos as $pais) {
                $iso = Cat020Pais::isoDesdeNombre($pais->nombre) ?? 'US';
                $nuevoId = $isoPorPaisId[$iso] ?? $isoPorPaisId['US'] ?? null;
                if ($nuevoId) {
                    DB::table('estados_paises')->where('pais_id', $pais->id)->update(['pais_id' => $nuevoId]);
                }
            }
        }

        DB::table('paises')->whereIn('id', $idsObsoletos)->delete();
    }
};
