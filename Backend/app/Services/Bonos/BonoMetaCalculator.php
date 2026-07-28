<?php

namespace App\Services\Bonos;

use App\Models\Admin\EmpresaFuncionalidad;
use App\Services\Ventas\VentaMontosPorVendedorService;
use Closure;
use Illuminate\Support\Facades\DB;

class BonoMetaCalculator
{
    private const SLUG_COMISIONES = 'comisiones-vendedores';

    private const SLUG_GIFT_CARDS = 'gift-cards';

    /** @var Closure(int): array<string, mixed> */
    private Closure $obtenerConfigComisiones;

    /** @var Closure(int): array<string, mixed> */
    private Closure $obtenerConfigGiftCards;

    /**
     * @param  Closure(int): array<string, mixed>|null  $obtenerConfigComisiones
     * @param  Closure(int): array<string, mixed>|null  $obtenerConfigGiftCards
     */
    public function __construct(
        ?Closure $obtenerConfigComisiones = null,
        ?Closure $obtenerConfigGiftCards = null,
    ) {
        $this->obtenerConfigComisiones = $obtenerConfigComisiones
            ?? fn (int $idEmpresa) => self::configFuncionalidad($idEmpresa, self::SLUG_COMISIONES);
        $this->obtenerConfigGiftCards = $obtenerConfigGiftCards
            ?? fn (int $idEmpresa) => self::configFuncionalidad($idEmpresa, self::SLUG_GIFT_CARDS);
    }

    public function ventasVendedorPeriodo(
        int $idEmpresa,
        int $idVendedor,
        string $fechaInicio,
        string $fechaFin,
    ): float {
        $exprVendedor = VentaMontosPorVendedorService::sqlIdVendedorEfectivo('dv', 'v');
        $idCategoriaGift = $this->idCategoriaGiftCards($idEmpresa);

        $query = DB::table('detalles_venta as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.id_venta')
            ->join('productos as p', 'p.id', '=', 'dv.id_producto')
            ->where('v.id_empresa', $idEmpresa)
            ->where('v.estado', 'Pagada')
            ->whereBetween('v.fecha', [$fechaInicio, $fechaFin])
            ->whereRaw("{$exprVendedor} = ?", [$idVendedor]);

        if ($idCategoriaGift !== null) {
            $query->where('p.id_categoria', '!=', $idCategoriaGift);
        }

        return (float) $query->sum(DB::raw('COALESCE(dv.total, 0) + COALESCE(dv.iva, 0)'));
    }

    private function idCategoriaGiftCards(int $idEmpresa): ?int
    {
        $comisiones = ($this->obtenerConfigComisiones)($idEmpresa);
        $id = $comisiones['id_categoria_gift_cards'] ?? null;
        if ($id !== null) {
            return (int) $id;
        }

        $gift = ($this->obtenerConfigGiftCards)($idEmpresa);
        $id = $gift['id_categoria_gift_cards'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    /** @return array<string, mixed> */
    private static function configFuncionalidad(int $idEmpresa, string $slug): array
    {
        $row = EmpresaFuncionalidad::query()
            ->where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->whereHas('funcionalidad', fn ($q) => $q->where('slug', $slug))
            ->first();

        return $row?->configuracion ?? [];
    }
}
