<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CAT-013 (MH): Cabañas Este = 10 y Cabañas Oeste = 11. El catálogo local los tenía invertidos y el DTE v2
 * rechaza el distrito ("VALOR NO ES PERMITIDO") porque no pertenece al municipio enviado.
 */
return new class extends Migration
{
    private const COLUMNAS = [
        'municipios' => 'cod',
        'distritos' => 'cod_municipio',
        'empresas' => 'cod_municipio',
        'sucursales' => 'cod_municipio',
        'clientes' => 'cod_municipio',
        'proveedores' => 'cod_municipio',
    ];

    public function up(): void
    {
        if ($this->codigoOeste() === '10') {
            $this->intercambiar();
        }
    }

    public function down(): void
    {
        if ($this->codigoOeste() === '11') {
            $this->intercambiar();
        }
    }

    private function codigoOeste(): ?string
    {
        return DB::table('municipios')
            ->where('cod_departamento', '09')
            ->where('nombre', 'like', '%OESTE')
            ->value('cod');
    }

    private function intercambiar(): void
    {
        DB::transaction(function () {
            foreach (self::COLUMNAS as $tabla => $columna) {
                $q = fn () => DB::table($tabla)->where('cod_departamento', '09');
                $q()->where($columna, '10')->update([$columna => 'XX']);
                $q()->where($columna, '11')->update([$columna => '10']);
                $q()->where($columna, 'XX')->update([$columna => '11']);
            }
        });
    }
};
