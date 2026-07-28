<?php

namespace App\Services\Comisiones;

use App\Models\Comisiones\ComisionCategoriaConfig;
use App\Models\Comisiones\ComisionSubcategoriaConfig;
use Closure;

class ComisionPorcentajeResolver
{
    /** @var Closure(int, int): float|int|string|null */
    private Closure $findCat;

    /** @var Closure(int, int): float|int|string|null */
    private Closure $findSub;

    /**
     * @param  Closure(int, int): float|int|string|null|null  $findCat
     * @param  Closure(int, int): float|int|string|null|null  $findSub
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

    /** @return Closure(int, int): float|int|string|null */
    private static function defaultFindCat(): Closure
    {
        return function (int $idEmpresa, int $idCategoria) {
            $config = ComisionCategoriaConfig::withoutGlobalScope('empresa')
                ->where('id_empresa', $idEmpresa)
                ->where('id_categoria', $idCategoria)
                ->first();

            return $config?->porcentaje;
        };
    }

    /** @return Closure(int, int): float|int|string|null */
    private static function defaultFindSub(): Closure
    {
        return function (int $idEmpresa, int $idSubcategoria) {
            $config = ComisionSubcategoriaConfig::withoutGlobalScope('empresa')
                ->where('id_empresa', $idEmpresa)
                ->where('id_subcategoria', $idSubcategoria)
                ->first();

            return $config?->porcentaje;
        };
    }

    public function resolver(int $idEmpresa, ?int $idCategoria, ?int $idSubcategoria): float
    {
        if ($idSubcategoria) {
            $override = ($this->findSub)($idEmpresa, $idSubcategoria);
            if ($override !== null) {
                return (float) $override;
            }
        }
        if ($idCategoria) {
            $pct = ($this->findCat)($idEmpresa, $idCategoria);
            if ($pct !== null) {
                return (float) $pct;
            }
        }

        return 0.0;
    }
}
