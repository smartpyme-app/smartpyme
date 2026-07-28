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
     * @param  Closure(int, int): float|int|string|null  $findCat
     * @param  Closure(int, int): float|int|string|null  $findSub
     */
    public function __construct(Closure $findCat, Closure $findSub)
    {
        $this->findCat = $findCat;
        $this->findSub = $findSub;
    }

    public static function fromDatabase(): self
    {
        return new self(
            function (int $idEmpresa, int $idCategoria) {
                $config = ComisionCategoriaConfig::withoutGlobalScope('empresa')
                    ->where('id_empresa', $idEmpresa)
                    ->where('id_categoria', $idCategoria)
                    ->first();

                return $config?->porcentaje;
            },
            function (int $idEmpresa, int $idSubcategoria) {
                $config = ComisionSubcategoriaConfig::withoutGlobalScope('empresa')
                    ->where('id_empresa', $idEmpresa)
                    ->where('id_subcategoria', $idSubcategoria)
                    ->first();

                return $config?->porcentaje;
            }
        );
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
