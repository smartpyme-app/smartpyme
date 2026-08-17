<?php

namespace App\Services\GiftCards;

use App\Models\Comisiones\ComisionMovimiento;
use App\Models\GiftCards\GiftCard;
use App\Models\GiftCards\GiftCardRedencion;
use App\Models\Ventas\Venta;
use App\Services\Comisiones\ComisionService;
use App\Services\Funcionalidades\FuncionalidadAccess;
use Carbon\Carbon;
use Closure;
use DateTimeInterface;

class GiftCardReverseService
{
    private const SLUG_GIFT = 'gift-cards';

    /** @var Closure(int, string): bool */
    private Closure $tieneFuncionalidad;

    /** @var Closure(int): \Illuminate\Support\Collection<int, object> */
    private Closure $obtenerRedencionesPendientes;

    /** @var Closure(int): ?object */
    private Closure $obtenerGiftCard;

    /** @var Closure(object): object */
    private Closure $persistirGiftCard;

    /** @var Closure(object): object */
    private Closure $persistirRedencion;

    /** @var Closure(int): ?object */
    private Closure $obtenerComisionMovimiento;

    /** @var Closure(object, float, bool, \DateTimeInterface): ?object */
    private Closure $ajustarComisionPorAnulacion;

    /** @var Closure(int): ?object */
    private Closure $obtenerVenta;

    /**
     * @param  Closure(int, string): bool|null  $tieneFuncionalidad
     * @param  Closure(int): \Illuminate\Support\Collection<int, object>|null  $obtenerRedencionesPendientes
     * @param  Closure(int): ?object|null  $obtenerGiftCard
     * @param  Closure(object): object|null  $persistirGiftCard
     * @param  Closure(object): object|null  $persistirRedencion
     * @param  Closure(int): ?object|null  $obtenerComisionMovimiento
     * @param  Closure(object, float, bool, \DateTimeInterface): ?object|null  $ajustarComisionPorAnulacion
     * @param  Closure(int): ?object|null  $obtenerVenta
     */
    public function __construct(
        ?Closure $tieneFuncionalidad = null,
        ?Closure $obtenerRedencionesPendientes = null,
        ?Closure $obtenerGiftCard = null,
        ?Closure $persistirGiftCard = null,
        ?Closure $persistirRedencion = null,
        ?Closure $obtenerComisionMovimiento = null,
        ?Closure $ajustarComisionPorAnulacion = null,
        ?Closure $obtenerVenta = null,
    ) {
        $this->tieneFuncionalidad = $tieneFuncionalidad
            ?? fn (int $idEmpresa, string $slug) => FuncionalidadAccess::empresaTieneSlug($idEmpresa, $slug);
        $this->obtenerRedencionesPendientes = $obtenerRedencionesPendientes
            ?? fn (int $idVenta) => GiftCardRedencion::withoutGlobalScope('empresa')
                ->where('id_venta', $idVenta)
                ->whereNull('reversed_at')
                ->lockForUpdate()
                ->get();
        $this->obtenerGiftCard = $obtenerGiftCard
            ?? fn (int $idGiftCard) => GiftCard::withoutGlobalScope('empresa')
                ->where('id', $idGiftCard)
                ->lockForUpdate()
                ->first();
        $this->persistirGiftCard = $persistirGiftCard
            ?? fn (object $card) => $card->save() ?: $card;
        $this->persistirRedencion = $persistirRedencion
            ?? fn (object $redencion) => $redencion->save() ?: $redencion;
        $this->obtenerComisionMovimiento = $obtenerComisionMovimiento
            ?? fn (int $id) => ComisionMovimiento::withoutGlobalScope('empresa')->find($id);
        $this->ajustarComisionPorAnulacion = $ajustarComisionPorAnulacion
            ?? fn (object $mov, float $montoBase, bool $completa, DateTimeInterface $fecha) => app(ComisionService::class)
                ->registrarAjustePorDevolucion($mov, $montoBase, $completa, $fecha);
        $this->obtenerVenta = $obtenerVenta
            ?? fn (int $idVenta) => Venta::withoutGlobalScopes()->find($idVenta);
    }

    public function revertirPorAnulacion(object $venta): void
    {
        $idEmpresa = (int) ($venta->id_empresa ?? 0);
        $idVenta = (int) ($venta->id ?? 0);
        if ($idEmpresa <= 0 || $idVenta <= 0) {
            return;
        }

        if (! ($this->tieneFuncionalidad)($idEmpresa, self::SLUG_GIFT)) {
            return;
        }

        $fechaEvento = Carbon::parse($venta->fecha ?? now());
        $redenciones = ($this->obtenerRedencionesPendientes)($idVenta);

        foreach ($redenciones as $redencion) {
            $this->revertirRedencion($redencion, $fechaEvento);
        }
    }

    public function syncPorDevolucion(object $devolucion): void
    {
        if (($devolucion->tipo ?? null) === 'descuento_ajuste') {
            return;
        }

        if (! ($devolucion->enable ?? false)) {
            return;
        }

        $idVenta = (int) ($devolucion->id_venta ?? 0);
        if ($idVenta <= 0) {
            return;
        }

        $venta = ($this->obtenerVenta)($idVenta);
        if ($venta === null) {
            return;
        }

        $totalVenta = (float) ($venta->total ?? 0);
        $totalDevolucion = (float) ($devolucion->total ?? 0);
        if ($totalVenta <= 0 || $totalDevolucion + 0.0001 < $totalVenta) {
            return;
        }

        $this->revertirPorAnulacion($venta);
    }

    private function revertirRedencion(object $redencion, DateTimeInterface $fechaEvento): void
    {
        if ($redencion->reversed_at !== null) {
            return;
        }

        $card = ($this->obtenerGiftCard)((int) $redencion->id_gift_card);
        if ($card === null) {
            return;
        }

        $monto = (float) $redencion->monto;
        $card->saldo = round((float) $card->saldo + $monto, 4);
        $montoInicial = (float) ($card->monto_inicial ?? $card->saldo);
        if ($card->saldo > $montoInicial) {
            $card->saldo = $montoInicial;
        }
        if ($card->saldo > 0 && ($card->estado ?? null) === GiftCard::ESTADO_AGOTADA) {
            $card->estado = GiftCard::ESTADO_ACTIVA;
        }
        ($this->persistirGiftCard)($card);

        $redencion->reversed_at = Carbon::now();
        ($this->persistirRedencion)($redencion);

        $idComision = (int) ($redencion->id_comision_movimiento ?? 0);
        if ($idComision <= 0) {
            return;
        }

        $movimiento = ($this->obtenerComisionMovimiento)($idComision);
        if ($movimiento === null) {
            return;
        }

        ($this->ajustarComisionPorAnulacion)(
            $movimiento,
            (float) $movimiento->monto_base,
            true,
            $fechaEvento
        );
    }
}
