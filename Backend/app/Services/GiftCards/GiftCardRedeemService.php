<?php

namespace App\Services\GiftCards;

use App\Models\GiftCards\GiftCard;
use App\Models\GiftCards\GiftCardRedencion;
use App\Models\Indicador;
use App\Models\Ventas\Venta;
use App\Services\Comisiones\ComisionService;
use App\Services\Funcionalidades\FuncionalidadAccess;
use Carbon\Carbon;
use Closure;
use DomainException;

class GiftCardRedeemService
{
    private const SLUG_GIFT = 'gift-cards';

    private const SLUG_COMISIONES = 'comisiones-vendedores';

    /** @var Closure(int, string): ?object */
    private Closure $buscarGiftCard;

    /** @var Closure(object): object */
    private Closure $persistirGiftCard;

    /** @var Closure(array<string, mixed>): object */
    private Closure $crearRedencion;

    /** @var Closure(int, string): bool */
    private Closure $tieneFuncionalidad;

    /** @var Closure(int, int, int, int, int, ?int, ?int, object, \DateTimeInterface): ?object */
    private Closure $registrarComisionRedencion;

    /**
     * @param  Closure(int, string): ?object|null  $buscarGiftCard
     * @param  Closure(object): object|null  $persistirGiftCard
     * @param  Closure(array<string, mixed>): object|null  $crearRedencion
     * @param  Closure(int, string): bool|null  $tieneFuncionalidad
     * @param  Closure(int, int, int, int, int, ?int, ?int, object, \DateTimeInterface): ?object|null  $registrarComisionRedencion
     */
    public function __construct(
        ?Closure $buscarGiftCard = null,
        ?Closure $persistirGiftCard = null,
        ?Closure $crearRedencion = null,
        ?Closure $tieneFuncionalidad = null,
        ?Closure $registrarComisionRedencion = null,
    ) {
        $this->buscarGiftCard = $buscarGiftCard
            ?? function (int $idEmpresa, string $codigo): ?object {
                return GiftCard::withoutGlobalScope('empresa')
                    ->where('id_empresa', $idEmpresa)
                    ->where('codigo', $codigo)
                    ->lockForUpdate()
                    ->first();
            };
        $this->persistirGiftCard = $persistirGiftCard
            ?? fn (object $card) => $card->save() ?: $card;
        $this->crearRedencion = $crearRedencion
            ?? fn (array $data) => GiftCardRedencion::withoutGlobalScope('empresa')->create($data);
        $this->tieneFuncionalidad = $tieneFuncionalidad
            ?? fn (int $idEmpresa, string $slug) => FuncionalidadAccess::empresaTieneSlug($idEmpresa, $slug);
        $this->registrarComisionRedencion = $registrarComisionRedencion
            ?? fn (
                int $idEmpresa,
                int $idVendedor,
                int $idVenta,
                int $idDetalleVenta,
                int $idGiftCardRedencion,
                ?int $idCategoria,
                ?int $idSubcategoria,
                object $detalleLinea,
                \DateTimeInterface $fechaEvento
            ) => app(ComisionService::class)->registrarDesdeRedencion(
                $idEmpresa,
                $idVendedor,
                $idVenta,
                $idDetalleVenta,
                $idGiftCardRedencion,
                $idCategoria,
                $idSubcategoria,
                $detalleLinea,
                $fechaEvento
            );
    }

    /**
     * Redime saldo de una gift card contra una venta pagada.
     *
     * @return object GiftCardRedencion
     */
    public function redeem(object $venta, string $codigo, float $monto, int $idVendedorAtencion): object
    {
        if ($monto <= 0) {
            throw new DomainException('Monto de redención inválido');
        }

        $idEmpresa = (int) $venta->id_empresa;
        $card = ($this->buscarGiftCard)($idEmpresa, $codigo);
        if ($card === null) {
            throw new DomainException('Gift card no encontrada');
        }

        $saldo = (float) $card->saldo;
        if ($saldo < $monto) {
            throw new DomainException('Saldo insuficiente');
        }

        $card->saldo = round($saldo - $monto, 4);
        if ($card->saldo <= 0) {
            $card->saldo = 0;
            $card->estado = GiftCard::ESTADO_AGOTADA;
        }
        ($this->persistirGiftCard)($card);

        [$idCategoria, $idSubcategoria, $idDetalleVenta, $detalleLinea] = $this->lineaComisionPrincipal($venta);

        $redencion = ($this->crearRedencion)([
            'id_empresa' => $idEmpresa,
            'id_gift_card' => (int) $card->id,
            'id_venta' => (int) $venta->id,
            'id_vendedor' => $idVendedorAtencion ?: null,
            'monto' => $monto,
            'saldo_resultante' => (float) $card->saldo,
            'id_categoria' => $idCategoria,
            'id_subcategoria' => $idSubcategoria,
            'id_comision_movimiento' => null,
        ]);

        if (($this->tieneFuncionalidad)($idEmpresa, self::SLUG_COMISIONES)
            && $detalleLinea !== null
            && $idDetalleVenta !== null
        ) {
            $fraccionGift = $this->fraccionGiftDesdeMonto($venta, $monto);
            $detalleEscalado = $this->escalarDetallePorFraccion($detalleLinea, $fraccionGift);
            $fechaEvento = Carbon::parse($venta->fecha_pago ?? $venta->fecha ?? now());

            $mov = ($this->registrarComisionRedencion)(
                $idEmpresa,
                $idVendedorAtencion,
                (int) $venta->id,
                $idDetalleVenta,
                (int) $redencion->id,
                $idCategoria,
                $idSubcategoria,
                $detalleEscalado,
                $fechaEvento
            );

            if ($mov !== null) {
                $redencion->id_comision_movimiento = $mov->id ?? null;
                if ($redencion instanceof GiftCardRedencion) {
                    $redencion->save();
                }
            }
        }

        return $redencion;
    }

    /**
     * Hook post-facturación: redime si la venta incluye pago gift card.
     *
     * Request esperado (v1, un código por venta):
     * - codigo_gift_card: string (requerido cuando hay pago gift)
     * - metodos_de_pago: [{ nombre, total }, ...] con nombre en FORMAS_PAGO_GIFT_CARD
     *   o forma_pago única gift card en la venta
     */
    public function redeemDesdeVenta(object $venta, object $request): void
    {
        $idEmpresa = (int) $venta->id_empresa;
        if (! ($this->tieneFuncionalidad)($idEmpresa, self::SLUG_GIFT)) {
            return;
        }

        $codigo = trim((string) ($request->codigo_gift_card ?? ''));
        if ($codigo === '') {
            return;
        }

        $montoGift = $this->montoPagoGiftDesdeRequest($venta, $request);
        if ($montoGift <= 0) {
            return;
        }

        if ($venta instanceof Venta) {
            $venta->loadMissing('detalles.producto');
        }

        $idVendedor = (int) ($venta->id_vendedor ?? 0);

        $this->redeem($venta, $codigo, $montoGift, $idVendedor);
    }

    private function montoPagoGiftDesdeRequest(object $venta, object $request): float
    {
        $monto = 0.0;

        $metodos = $request->metodos_de_pago ?? $request['metodos_de_pago'] ?? null;
        if (is_array($metodos)) {
            foreach ($metodos as $metodo) {
                $nombre = is_array($metodo) ? ($metodo['nombre'] ?? null) : ($metodo->nombre ?? null);
                if (Indicador::esFormaPagoGiftCard($nombre)) {
                    $monto += (float) (is_array($metodo) ? ($metodo['total'] ?? 0) : ($metodo->total ?? 0));
                }
            }
        }

        if ($monto > 0) {
            return $monto;
        }

        $formaPago = $venta->forma_pago ?? $request->forma_pago ?? null;
        if (Indicador::esFormaPagoGiftCard($formaPago)) {
            return (float) ($venta->total ?? 0);
        }

        return 0.0;
    }

    private function fraccionGiftDesdeMonto(object $venta, float $montoGift): float
    {
        $totalVenta = (float) ($venta->total ?? 0);
        if ($totalVenta <= 0) {
            return 0.0;
        }

        return min(1.0, $montoGift / $totalVenta);
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: ?int, 3: ?object}
     */
    private function lineaComisionPrincipal(object $venta): array
    {
        foreach ($venta->detalles as $detalle) {
            $producto = $detalle->producto ?? null;
            if ($producto === null) {
                continue;
            }

            $idCategoria = (int) ($producto->id_categoria ?? 0) ?: null;
            $idSubcategoria = ! empty($producto->subcategoria_id) ? (int) $producto->subcategoria_id : null;

            return [
                $idCategoria,
                $idSubcategoria,
                (int) $detalle->id,
                $detalle,
            ];
        }

        return [null, null, null, null];
    }

    private function escalarDetallePorFraccion(object $detalle, float $fraccion): object
    {
        $factor = max(0.0, min(1.0, $fraccion));
        $scaled = clone $detalle;

        foreach (['gravada', 'exenta', 'no_sujeta', 'total', 'sub_total'] as $campo) {
            if (isset($scaled->{$campo})) {
                $scaled->{$campo} = round((float) $scaled->{$campo} * $factor, 4);
            }
        }

        return $scaled;
    }
}
