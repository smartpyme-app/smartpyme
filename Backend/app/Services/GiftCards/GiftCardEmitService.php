<?php

namespace App\Services\GiftCards;

use App\Models\Admin\EmpresaFuncionalidad;
use App\Models\GiftCards\GiftCard;
use App\Models\Ventas\Venta;
use App\Services\Funcionalidades\FuncionalidadAccess;
use Carbon\Carbon;
use Closure;

class GiftCardEmitService
{
    private const SLUG = 'gift-cards';

    /** @var Closure(int, string): bool */
    private Closure $tieneFuncionalidad;

    /** @var Closure(int): array<string, mixed> */
    private Closure $obtenerConfig;

    /** @var Closure(array<string, mixed>, array<string, mixed>): object */
    private Closure $persistirGiftCard;

    /** @var Closure(int): string */
    private Closure $generarCodigo;

    /**
     * @param  Closure(int, string): bool|null  $tieneFuncionalidad
     * @param  Closure(int): array<string, mixed>|null  $obtenerConfig
     * @param  Closure(array<string, mixed>, array<string, mixed>): object|null  $persistirGiftCard
     * @param  Closure(int): string|null  $generarCodigo
     */
    public function __construct(
        ?GiftCardCodeGenerator $codeGenerator = null,
        ?Closure $tieneFuncionalidad = null,
        ?Closure $obtenerConfig = null,
        ?Closure $persistirGiftCard = null,
        ?Closure $generarCodigo = null,
    ) {
        $generator = $codeGenerator ?? new GiftCardCodeGenerator();

        $this->tieneFuncionalidad = $tieneFuncionalidad
            ?? fn (int $idEmpresa, string $slug) => FuncionalidadAccess::empresaTieneSlug($idEmpresa, $slug);
        $this->obtenerConfig = $obtenerConfig
            ?? fn (int $idEmpresa) => self::configFuncionalidad($idEmpresa);
        $this->persistirGiftCard = $persistirGiftCard
            ?? fn (array $where, array $values) => GiftCard::withoutGlobalScope('empresa')
                ->firstOrCreate($where, $values);
        $this->generarCodigo = $generarCodigo
            ?? fn (int $idEmpresa) => $generator->generateUnique($idEmpresa);
    }

    public function emitirDesdeVenta(object $venta): void
    {
        $idEmpresa = (int) $venta->id_empresa;

        if (! ($this->tieneFuncionalidad)($idEmpresa, self::SLUG)) {
            return;
        }

        $config = ($this->obtenerConfig)($idEmpresa);
        $idCategoriaGiftCards = $config['id_categoria_gift_cards'] ?? null;
        if ($idCategoriaGiftCards === null) {
            return;
        }
        $idCategoriaGiftCards = (int) $idCategoriaGiftCards;

        if ($venta instanceof Venta) {
            $venta->loadMissing('detalles.producto');
        }

        foreach ($venta->detalles as $detalle) {
            $producto = $detalle->producto ?? null;
            if ($producto === null) {
                continue;
            }

            $idCategoria = (int) ($producto->id_categoria ?? 0);
            if ($idCategoria !== $idCategoriaGiftCards) {
                continue;
            }

            $monto = (float) $detalle->total;
            if ($monto <= 0) {
                continue;
            }

            $idVendedor = (int) ($detalle->id_vendedor ?: $venta->id_vendedor ?: 0) ?: null;

            ($this->persistirGiftCard)([
                'id_empresa' => $idEmpresa,
                'id_detalle_venta_emision' => (int) $detalle->id,
            ], [
                'codigo' => ($this->generarCodigo)($idEmpresa),
                'monto_inicial' => $monto,
                'saldo' => $monto,
                'fecha_emision' => Carbon::now(),
                'fecha_vencimiento' => null,
                'id_vendedor_emisor' => $idVendedor,
                'id_venta_emision' => (int) $venta->id,
                'id_producto' => $detalle->id_producto,
                'estado' => GiftCard::ESTADO_ACTIVA,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private static function configFuncionalidad(int $idEmpresa): array
    {
        $row = EmpresaFuncionalidad::query()
            ->where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->whereHas('funcionalidad', fn ($q) => $q->where('slug', self::SLUG))
            ->first();

        return $row?->configuracion ?? [];
    }
}
