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

    /** @var array<string, string>|null */
    private ?array $mapProvinciaNombre = null;

    /** @var array<string, string>|null */
    private ?array $mapCantonNombre = null;

    /** @var array<string, string>|null */
    private ?array $mapDistritoNombre = null;

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

    /**
     * El XML FE-CR guarda provincia + cantón/distrito de 2 dígitos; el PDF debe mostrar nombres INEC.
     *
     * @param  array{province?: mixed, canton?: mixed, district?: mixed, address_details?: string, neighborhood?: string}  $location
     * @return array{province: string, canton: string, district: string}
     */
    public function etiquetasUbicacionParaRepresentacionGrafica(array $location): array
    {
        $prov = $location['province'] ?? null;
        $provCod = is_array($prov) ? (string) ($prov['code'] ?? '') : (string) $prov;
        $canRaw = (string) ($location['canton'] ?? '');
        $disRaw = (string) ($location['district'] ?? '');

        $cod5 = $this->codigoDistritoInec5DesdeUbicacionXml($prov, $canRaw, $disRaw);
        $pKey = (string) (int) (preg_replace('/\D/', '', $provCod) ?? '');
        $nombreProv = $pKey !== '0' ? ($this->mapProvinciaNombre()[$pKey] ?? null) : null;

        $codCant3 = null;
        if ($cod5 !== null && strlen($cod5) === 5) {
            $codCant3 = substr($cod5, 0, 3);
        } else {
            $cDigits = preg_replace('/\D/', '', $canRaw) ?? '';
            if (strlen($cDigits) === 3) {
                $codCant3 = $cDigits;
            } elseif ($pKey !== '0' && $cDigits !== '') {
                $codCant3 = $pKey.str_pad(substr($cDigits, -2), 2, '0', STR_PAD_LEFT);
            }
        }

        $nombreCant = $codCant3 !== null ? ($this->mapCantonNombre()[$codCant3] ?? null) : null;
        $nombreDist = $cod5 !== null ? ($this->mapDistritoNombre()[$cod5] ?? null) : null;

        return [
            'province' => $nombreProv ?? ($provCod !== '' ? $provCod : ''),
            'canton' => $nombreCant ?? $canRaw,
            'district' => $nombreDist ?? $disRaw,
        ];
    }

    /**
     * Reconstruye el código distrito INEC de 5 dígitos a partir de nodos Ubicacion del XML v4.4.
     */
    public function codigoDistritoInec5DesdeUbicacionXml(mixed $provincia, mixed $canton, mixed $distrito): ?string
    {
        $p = preg_replace('/\D/', '', (string) (is_array($provincia) ? ($provincia['code'] ?? '') : $provincia)) ?? '';
        $c = preg_replace('/\D/', '', (string) $canton) ?? '';
        $d = preg_replace('/\D/', '', (string) $distrito) ?? '';
        if ($p === '') {
            return null;
        }
        if (strlen($d) === 5 && preg_match('/^[1-7]\d{4}$/', $d) === 1) {
            return $d;
        }
        if (strlen($c) === 3 && $d !== '') {
            return $c.str_pad(substr($d, -2), 2, '0', STR_PAD_LEFT);
        }
        if ($c !== '' && $d !== '') {
            return $p.str_pad(substr($c, -2), 2, '0', STR_PAD_LEFT).str_pad(substr($d, -2), 2, '0', STR_PAD_LEFT);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function mapProvinciaNombre(): array
    {
        if ($this->mapProvinciaNombre !== null) {
            return $this->mapProvinciaNombre;
        }
        $map = [];
        foreach ($this->departamentos() as $row) {
            $cod = (string) (int) ($row['cod'] ?? '');
            if ($cod === '0') {
                continue;
            }
            $map[$cod] = $this->tituloUbicacion((string) ($row['nombre'] ?? ''));
        }
        $this->mapProvinciaNombre = $map;

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function mapCantonNombre(): array
    {
        if ($this->mapCantonNombre !== null) {
            return $this->mapCantonNombre;
        }
        $map = [];
        foreach ($this->municipios() as $row) {
            $cod = preg_replace('/\D/', '', (string) ($row['cod'] ?? '')) ?? '';
            if (strlen($cod) !== 3) {
                continue;
            }
            $map[$cod] = $this->tituloUbicacion((string) ($row['nombre'] ?? ''));
        }
        $this->mapCantonNombre = $map;

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function mapDistritoNombre(): array
    {
        if ($this->mapDistritoNombre !== null) {
            return $this->mapDistritoNombre;
        }
        $map = [];
        foreach ($this->distritos() as $row) {
            $cod = preg_replace('/\D/', '', (string) ($row['cod'] ?? '')) ?? '';
            if (strlen($cod) !== 5) {
                continue;
            }
            $map[$cod] = $this->tituloUbicacion((string) ($row['nombre'] ?? ''));
        }
        $this->mapDistritoNombre = $map;

        return $map;
    }

    private function tituloUbicacion(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
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
