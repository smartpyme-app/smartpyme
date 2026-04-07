<?php

namespace App\Services\FacturacionElectronica\CostaRica;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

/**
 * Catálogo territorial INEC/DGT (mismos JSON que dazza-dev/dgt-xml-generator: provincias, cantones, distritos).
 * Expone el mismo formato que MH (El Salvador) para reutilizar selects y localStorage en el frontend.
 */
final class CostaRicaDgtUbicacionCatalogService
{
    private const CACHE_TTL = 86400;

    /**
     * @return list<array{cod: string, nombre: string}>
     */
    public function departamentos(): array
    {
        return Cache::remember('fe_cr_dgt_departamentos', self::CACHE_TTL, function () {
            $rows = $this->readJsonFile('provincias');

            $out = [];
            foreach ($rows as $row) {
                $code = isset($row['code']) ? (string) $row['code'] : '';
                $name = isset($row['name']) ? (string) $row['name'] : '';
                if ($code === '' || $name === '') {
                    continue;
                }
                $out[] = ['cod' => $code, 'nombre' => $name];
            }

            return $out;
        });
    }

    /**
     * @return list<array{cod: string, nombre: string, cod_departamento: string, nombre_departamento: string}>
     */
    public function municipios(): array
    {
        return Cache::remember('fe_cr_dgt_municipios', self::CACHE_TTL, function () {
            $provincias = $this->departamentos();
            $nombrePorCod = [];
            foreach ($provincias as $p) {
                $nombrePorCod[$p['cod']] = $p['nombre'];
            }

            $rows = $this->readJsonFile('cantones');
            $out = [];
            foreach ($rows as $row) {
                $code = isset($row['code']) ? (string) $row['code'] : '';
                $name = isset($row['name']) ? (string) $row['name'] : '';
                if (strlen($code) < 3) {
                    continue;
                }
                $codDep = $code[0];
                $out[] = [
                    'cod' => $code,
                    'nombre' => $name,
                    'cod_departamento' => $codDep,
                    'nombre_departamento' => $nombrePorCod[$codDep] ?? '',
                ];
            }

            return $out;
        });
    }

    /**
     * @return list<array{cod: string, nombre: string, cod_municipio: string, cod_departamento: string}>
     */
    public function distritos(): array
    {
        return Cache::remember('fe_cr_dgt_distritos', self::CACHE_TTL, function () {
            $rows = $this->readJsonFile('distritos');
            $out = [];
            foreach ($rows as $row) {
                $code = isset($row['code']) ? preg_replace('/\D/', '', (string) $row['code']) : '';
                $name = isset($row['name']) ? (string) $row['name'] : '';
                if (strlen($code) !== 5) {
                    continue;
                }
                $codDep = $code[0];
                $codMun = substr($code, 0, 3);
                $out[] = [
                    'cod' => $code,
                    'nombre' => $name,
                    'cod_municipio' => $codMun,
                    'cod_departamento' => $codDep,
                ];
            }

            return $out;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readJsonFile(string $baseName): array
    {
        $path = $this->resolveDataPath($baseName);
        if (! is_readable($path)) {
            throw new RuntimeException("No se encontró el catálogo DGT '{$baseName}.json' en {$path}. Ejecute composer install.");
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("No se pudo leer el catálogo DGT: {$path}");
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException("JSON inválido en catálogo DGT: {$baseName}.json");
        }

        return $data;
    }

    private function resolveDataPath(string $baseName): string
    {
        $candidates = [
            base_path('vendor/dazza-dev/dgt-xml-generator/src/Data/'.$baseName.'.json'),
            base_path('vendor/dazza-dev/dgt-cr/vendor/dazza-dev/dgt-xml-generator/src/Data/'.$baseName.'.json'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $candidates[0];
    }
}
