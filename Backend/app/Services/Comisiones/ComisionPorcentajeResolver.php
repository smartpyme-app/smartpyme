<?php

namespace App\Services\Comisiones;

use App\Models\Comisiones\ComisionCategoriaConfig;
use App\Models\Comisiones\ComisionSubcategoriaConfig;
use Closure;

class ComisionPorcentajeResolver
{
    /** @var Closure(int, int, ?int): float|int|string|null */
    private Closure $findCat;

    /** @var Closure(int, int, ?int): float|int|string|null */
    private Closure $findSub;

    /**
     * @param  Closure(int, int, ?int): float|int|string|null|null  $findCat
     * @param  Closure(int, int, ?int): float|int|string|null|null  $findSub
     */
    public function __construct(?Closure $findCat = null, ?Closure $findSub = null)
    {
        // Defaults for container DI; tests can inject stubs.
        $this->findCat = $findCat ?? self::defaultFindCat();
        $this->findSub = $findSub ?? self::defaultFindSub();
    }

    public static function fromDatabase(): self
    {
        return new self();
    }

    /** @return Closure(int, int, ?int): float|int|string|null */
    private static function defaultFindCat(): Closure
    {
        return function (int $idEmpresa, int $idCategoria, ?int $idRegla = null) {
            $query = ComisionCategoriaConfig::withoutGlobalScope('empresa')
                ->where('id_empresa', $idEmpresa)
                ->where('id_categoria', $idCategoria);
            if ($idRegla !== null) {
                $query->where('id_regla', $idRegla);
            }

            return $query->first()?->porcentaje;
        };
    }

    /** @return Closure(int, int, ?int): float|int|string|null */
    private static function defaultFindSub(): Closure
    {
        return function (int $idEmpresa, int $idSubcategoria, ?int $idRegla = null) {
            $query = ComisionSubcategoriaConfig::withoutGlobalScope('empresa')
                ->where('id_empresa', $idEmpresa)
                ->where('id_subcategoria', $idSubcategoria);
            if ($idRegla !== null) {
                $query->where('id_regla', $idRegla);
            }

            return $query->first()?->porcentaje;
        };
    }

    public function resolver(int $idEmpresa, ?int $idCategoria, ?int $idSubcategoria, ?int $idRegla = null): float
    {
        if ($idSubcategoria) {
            $override = ($this->findSub)($idEmpresa, $idSubcategoria, $idRegla);
            if ($override !== null) {
                return (float) $override;
            }
        }
        if ($idCategoria) {
            $pct = ($this->findCat)($idEmpresa, $idCategoria, $idRegla);
            if ($pct !== null) {
                return (float) $pct;
            }
        }

        return 0.0;
    }
}
