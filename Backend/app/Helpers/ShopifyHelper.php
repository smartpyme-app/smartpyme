<?php

namespace App\Helpers;

use App\Constants\ShopifyConstant;
use App\Models\MH\Departamento;
use App\Models\MH\Distrito;
use App\Models\MH\Municipio;
use App\Models\Ventas\Clientes\Cliente;

class ShopifyHelper
{
    /**
     * Resuelve los códigos y nombres oficiales de Departamento, Municipio y Distrito según catálogos de MH de El Salvador.
     *
     * @param string|null $city
     * @param string|null $provinceCode
     * @param string|null $provinceName
     * @param string|null $countryCode
     * @return array
     */
    public static function resolverUbicacionElSalvador(?string $city, ?string $provinceCode, ?string $provinceName, ?string $countryCode = 'SV'): array
    {
        $city = trim((string) $city);
        $provinceCode = trim((string) $provinceCode);
        $provinceName = trim((string) $provinceName);
        $countryCode = strtoupper(trim((string) $countryCode));

        // Si no es El Salvador, retornar valores sin mapeo MH
        if (!empty($countryCode) && !in_array($countryCode, ['SV', 'SLV', 'EL SALVADOR', 'EL_SALVADOR'])) {
            return [
                'departamento' => $provinceName ?: null,
                'cod_departamento' => null,
                'municipio' => $city ?: null,
                'cod_municipio' => null,
                'distrito' => null,
                'cod_distrito' => null,
            ];
        }

        // 1. Resolver Departamento
        $codDepartamento = ShopifyConstant::obtenerCodigoDepartamento($provinceCode);
        if (!$codDepartamento && !empty($provinceName)) {
            try {
                $codDepartamento = Departamento::whereRaw('LOWER(TRIM(nombre)) = ?', [strtolower($provinceName)])->value('cod');
            } catch (\Throwable $e) {
                $codDepartamento = null;
            }
        }

        $nombreDepartamento = null;
        if ($codDepartamento) {
            try {
                $nombreDepartamento = Departamento::where('cod', $codDepartamento)->value('nombre');
            } catch (\Throwable $e) {
                $nombreDepartamento = null;
            }
        }
        if (!$nombreDepartamento && $codDepartamento) {
            $nombreDepartamento = ShopifyConstant::MAPEO_DEPARTAMENTOS_NOMBRES[$codDepartamento] ?? $provinceName;
        }
        if (!$nombreDepartamento) {
            $nombreDepartamento = $provinceName ?: null;
        }

        $distrito = null;
        $codDistrito = null;
        $municipio = $city ?: null;
        $codMunicipio = null;

        if (!empty($city)) {
            $cityLower = strtolower($city);

            try {
                // 2. Buscar coincidencia en Distritos (prioridad con filtro de departamento)
                $queryDistrito = Distrito::whereRaw('LOWER(TRIM(nombre)) = ?', [$cityLower]);
                if ($codDepartamento) {
                    $queryDistrito->where('cod_departamento', $codDepartamento);
                }
                $distritoModel = $queryDistrito->first();

                // Si no se encontró exacto con departamento, intentar sin filtro si no había departamento
                if (!$distritoModel && !$codDepartamento) {
                    $distritoModel = Distrito::whereRaw('LOWER(TRIM(nombre)) = ?', [$cityLower])->first();
                }

                // Si aún no se encuentra, intentar coincidencia parcial LIKE con departamento
                if (!$distritoModel && $codDepartamento) {
                    $distritoModel = Distrito::where('cod_departamento', $codDepartamento)
                        ->whereRaw('LOWER(nombre) LIKE ?', ['%' . $cityLower . '%'])
                        ->first();
                }

                if ($distritoModel) {
                    $distrito = $distritoModel->nombre;
                    $codDistrito = $distritoModel->cod;
                    $codMunicipio = $distritoModel->cod_municipio;

                    // Obtener nombre oficial del municipio desde tabla municipios
                    $nombreMuni = null;
                    try {
                        $nombreMuni = Municipio::where('cod', $codMunicipio)
                            ->where('cod_departamento', $distritoModel->cod_departamento)
                            ->value('nombre');
                    } catch (\Throwable $e) {
                        $nombreMuni = null;
                    }
                    $municipio = $nombreMuni ?: ($distritoModel->nombre_municipio ?? $distritoModel->municipio?->nombre ?? $city);

                    if (!$codDepartamento) {
                        $codDepartamento = $distritoModel->cod_departamento;
                    }
                    if (!$nombreDepartamento && $codDepartamento) {
                        try {
                            $nombreDepartamento = Departamento::where('cod', $codDepartamento)->value('nombre');
                        } catch (\Throwable $e) {
                            $nombreDepartamento = null;
                        }
                    }
                } else {
                    // 3. Si no es Distrito, buscar en Municipios
                    $queryMunicipio = Municipio::whereRaw('LOWER(TRIM(nombre)) = ?', [$cityLower]);
                    if ($codDepartamento) {
                        $queryMunicipio->where('cod_departamento', $codDepartamento);
                    }
                    $municipioModel = $queryMunicipio->first();

                    if (!$municipioModel && $codDepartamento) {
                        $municipioModel = Municipio::where('cod_departamento', $codDepartamento)
                            ->whereRaw('LOWER(nombre) LIKE ?', ['%' . $cityLower . '%'])
                            ->first();
                    }

                    if ($municipioModel) {
                        $municipio = $municipioModel->nombre;
                        $codMunicipio = $municipioModel->cod;
                        if (!$codDepartamento) {
                            $codDepartamento = $municipioModel->cod_departamento;
                        }
                        if (!$nombreDepartamento && $codDepartamento) {
                            try {
                                $nombreDepartamento = Departamento::where('cod', $codDepartamento)->value('nombre');
                            } catch (\Throwable $e) {
                                $nombreDepartamento = null;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silently fallback if DB query is not available
            }
        }

        return [
            'departamento' => $nombreDepartamento,
            'cod_departamento' => $codDepartamento,
            'municipio' => $municipio,
            'cod_municipio' => $codMunicipio,
            'distrito' => $distrito,
            'cod_distrito' => $codDistrito,
        ];
    }

    /**
     * Obtiene o crea el cliente "Consumidor Final" por defecto para la empresa.
     *
     * @param int $empresaId
     * @return Cliente
     */
    public static function obtenerClienteConsumidorFinal(int $empresaId): Cliente
    {
        // Buscar cliente "Consumidor Final" existente en la empresa
        $cliente = Cliente::where('id_empresa', $empresaId)
            ->where(function ($query) {
                $query->where('nombre', 'Consumidor Final')
                      ->orWhere('nombre', 'LIKE', '%Consumidor Final%')
                      ->orWhere('nombre_empresa', 'Consumidor Final');
            })
            ->first();

        if (!$cliente) {
            // Crear cliente "Consumidor Final" si no existe
            $cliente = Cliente::create([
                'nombre' => 'Consumidor Final',
                'apellido' => '',
                'correo' => null,
                'telefono' => null,
                'direccion' => null,
                'pais' => 'El Salvador',
                'cod_pais' => 'SV',
                'municipio' => null,
                'cod_municipio' => null,
                'distrito' => null,
                'cod_distrito' => null,
                'departamento' => null,
                'cod_departamento' => null,
                'tipo' => 'Persona',
                'enable' => 1,
                'id_empresa' => $empresaId,
                'id_usuario' => null,
            ]);
        }

        return $cliente;
    }
}
