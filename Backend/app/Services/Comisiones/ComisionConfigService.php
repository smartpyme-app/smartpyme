<?php

namespace App\Services\Comisiones;

use App\Models\Comisiones\ComisionCategoriaConfig;
use App\Models\Comisiones\ComisionRegla;
use App\Models\Comisiones\ComisionSubcategoriaConfig;
use App\Models\Inventario\Categorias\Categoria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

class ComisionConfigService
{
    /** @return Collection<int, array<string, mixed>> */
    public function listarCategorias(int $idEmpresa): Collection
    {
        $configs = ComisionCategoriaConfig::query()
            ->where('id_empresa', $idEmpresa)
            ->get()
            ->keyBy('id_categoria');

        $subConfigs = ComisionSubcategoriaConfig::query()
            ->where('id_empresa', $idEmpresa)
            ->get()
            ->keyBy('id_subcategoria');

        $hijos = Categoria::query()
            ->where('id_empresa', $idEmpresa)
            ->where('enable', '1')
            ->where(function ($q) {
                $q->where('subcategoria', 1)
                    ->orWhereNotNull('id_cate_padre');
            })
            ->orderBy('nombre')
            ->get()
            ->groupBy(fn (Categoria $c) => (int) ($c->id_cate_padre ?? 0));

        // Padres: filas de categorias que no son subcategoría
        return Categoria::query()
            ->where('id_empresa', $idEmpresa)
            ->where('enable', '1')
            ->where(function ($q) {
                $q->where('subcategoria', 0)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('subcategoria')
                            ->whereNull('id_cate_padre');
                    });
            })
            ->orderBy('nombre')
            ->get()
            ->map(function (Categoria $categoria) use ($configs, $subConfigs, $hijos) {
                $config = $configs->get($categoria->id);

                $subcategorias = ($hijos->get($categoria->id) ?? collect())
                    ->map(function (Categoria $sub) use ($subConfigs) {
                        $subConfig = $subConfigs->get($sub->id);

                        return [
                            'id_subcategoria' => $sub->id,
                            'nombre' => $sub->nombre,
                            'porcentaje' => $subConfig !== null ? (float) $subConfig->porcentaje : null,
                            'config_id' => $subConfig?->id,
                        ];
                    })
                    ->values();

                return [
                    'id_categoria' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'porcentaje' => $config !== null ? (float) $config->porcentaje : 0.0,
                    'config_id' => $config?->id,
                    'subcategorias' => $subcategorias,
                ];
            });
    }

    public function actualizarCategoria(int $idEmpresa, int $idCategoria, float $porcentaje): ComisionCategoriaConfig
    {
        $this->assertCategoriaEmpresa($idEmpresa, $idCategoria);
        $this->assertPorcentajeValido($porcentaje);
        $idRegla = $this->idReglaCategoriaDefault($idEmpresa);

        return ComisionCategoriaConfig::query()->updateOrCreate(
            [
                'id_empresa' => $idEmpresa,
                'id_regla' => $idRegla,
                'id_categoria' => $idCategoria,
            ],
            ['porcentaje' => $porcentaje]
        );
    }

    public function actualizarSubcategoria(int $idEmpresa, int $idSubcategoria, float $porcentaje): ComisionSubcategoriaConfig
    {
        $this->assertSubcategoriaEmpresa($idEmpresa, $idSubcategoria);
        $this->assertPorcentajeValido($porcentaje);
        $idRegla = $this->idReglaCategoriaDefault($idEmpresa);

        return ComisionSubcategoriaConfig::query()->updateOrCreate(
            [
                'id_empresa' => $idEmpresa,
                'id_regla' => $idRegla,
                'id_subcategoria' => $idSubcategoria,
            ],
            ['porcentaje' => $porcentaje]
        );
    }

    private function idReglaCategoriaDefault(int $idEmpresa): int
    {
        $regla = ComisionRegla::query()
            ->where('id_empresa', $idEmpresa)
            ->where('tipo_calculo', ComisionRegla::TIPO_POR_CATEGORIA)
            ->where('alcance', ComisionRegla::ALCANCE_GLOBAL)
            ->orderBy('id')
            ->first();

        if ($regla !== null) {
            return (int) $regla->id;
        }

        return DB::transaction(function () use ($idEmpresa) {
            $regla = ComisionRegla::query()
                ->where('id_empresa', $idEmpresa)
                ->where('tipo_calculo', ComisionRegla::TIPO_POR_CATEGORIA)
                ->where('alcance', ComisionRegla::ALCANCE_GLOBAL)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($regla === null) {
                $regla = ComisionRegla::query()->create([
                    'id_empresa' => $idEmpresa,
                    'nombre' => 'Por categoría',
                    'tipo_calculo' => ComisionRegla::TIPO_POR_CATEGORIA,
                    'alcance' => ComisionRegla::ALCANCE_GLOBAL,
                    'id_vendedores' => null,
                    'momento_devengo' => ComisionRegla::MOMENTO_AL_PAGAR,
                    'reemplaza_global' => false,
                    'config' => new stdClass(),
                    'activo' => true,
                ]);
            }

            return (int) $regla->id;
        });
    }

    private function assertPorcentajeValido(float $porcentaje): void
    {
        if ($porcentaje < 0 || $porcentaje > 100) {
            throw ValidationException::withMessages([
                'porcentaje' => ['El porcentaje debe estar entre 0 y 100.'],
            ]);
        }
    }

    private function assertCategoriaEmpresa(int $idEmpresa, int $idCategoria): void
    {
        $exists = Categoria::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id', $idCategoria)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'id_categoria' => ['La categoría no pertenece a la empresa.'],
            ]);
        }
    }

    private function assertSubcategoriaEmpresa(int $idEmpresa, int $idSubcategoria): void
    {
        // Subcategorías = filas hijas en `categorias` (no existe categoria_subcategorias en prod)
        $exists = Categoria::query()
            ->where('id_empresa', $idEmpresa)
            ->where('id', $idSubcategoria)
            ->where(function ($q) {
                $q->where('subcategoria', 1)
                    ->orWhereNotNull('id_cate_padre');
            })
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'id_subcategoria' => ['La subcategoría no pertenece a la empresa.'],
            ]);
        }
    }
}
