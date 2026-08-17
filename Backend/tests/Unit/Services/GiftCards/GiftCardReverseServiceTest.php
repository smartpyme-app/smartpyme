<?php

namespace Tests\Unit\Services\GiftCards;

use App\Models\GiftCards\GiftCard;
use App\Services\GiftCards\GiftCardReverseService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use stdClass;

class GiftCardReverseServiceTest extends TestCase
{
    /** @param  array<string, mixed>  $overrides */
    private function makeRedencion(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 10,
            'id_gift_card' => 1,
            'id_venta' => 50,
            'monto' => 40.0,
            'id_comision_movimiento' => null,
            'reversed_at' => null,
        ], $overrides);
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeCard(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'saldo' => 0.0,
            'monto_inicial' => 100.0,
            'estado' => GiftCard::ESTADO_AGOTADA,
        ], $overrides);
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeService(array $overrides = []): GiftCardReverseService
    {
        $defaults = [
            'tieneFuncionalidad' => fn () => true,
            'obtenerRedencionesPendientes' => fn () => collect([]),
            'obtenerGiftCard' => fn () => null,
            'persistirGiftCard' => fn (object $card) => $card,
            'persistirRedencion' => fn (object $redencion) => $redencion,
            'obtenerComisionMovimiento' => fn () => null,
            'ajustarComisionPorAnulacion' => fn () => null,
            'obtenerVenta' => fn () => null,
        ];

        $config = array_merge($defaults, $overrides);

        return new GiftCardReverseService(
            $config['tieneFuncionalidad'],
            $config['obtenerRedencionesPendientes'],
            $config['obtenerGiftCard'],
            $config['persistirGiftCard'],
            $config['persistirRedencion'],
            $config['obtenerComisionMovimiento'],
            $config['ajustarComisionPorAnulacion'],
            $config['obtenerVenta'],
        );
    }

    public function test_revertir_restaura_saldo_y_activa_tarjeta(): void
    {
        $card = $this->makeCard();
        $redencion = $this->makeRedencion();
        $savedCard = null;
        $savedRedencion = null;

        $svc = $this->makeService([
            'obtenerRedencionesPendientes' => fn () => collect([$redencion]),
            'obtenerGiftCard' => fn () => $card,
            'persistirGiftCard' => function (object $c) use (&$savedCard) {
                $savedCard = $c;

                return $c;
            },
            'persistirRedencion' => function (object $r) use (&$savedRedencion) {
                $savedRedencion = $r;

                return $r;
            },
        ]);

        $venta = (object) ['id' => 50, 'id_empresa' => 1, 'fecha' => '2026-07-20'];
        $svc->revertirPorAnulacion($venta);

        $this->assertSame(40.0, (float) $savedCard->saldo);
        $this->assertSame(GiftCard::ESTADO_ACTIVA, $savedCard->estado);
        $this->assertNotNull($savedRedencion->reversed_at);
    }

    public function test_revertir_idempotente_no_duplica_saldo(): void
    {
        $card = $this->makeCard(['saldo' => 40.0, 'estado' => GiftCard::ESTADO_ACTIVA]);
        $redencion = $this->makeRedencion(['reversed_at' => Carbon::parse('2026-07-20')]);
        $persistCardCalls = 0;

        $svc = $this->makeService([
            'obtenerRedencionesPendientes' => fn () => collect([$redencion]),
            'obtenerGiftCard' => fn () => $card,
            'persistirGiftCard' => function (object $c) use (&$persistCardCalls) {
                $persistCardCalls++;

                return $c;
            },
        ]);

        $venta = (object) ['id' => 50, 'id_empresa' => 1, 'fecha' => '2026-07-20'];
        $svc->revertirPorAnulacion($venta);

        $this->assertSame(0, $persistCardCalls);
        $this->assertSame(40.0, (float) $card->saldo);
    }

    public function test_revertir_ajusta_comision_ligada(): void
    {
        $card = $this->makeCard();
        $redencion = $this->makeRedencion(['id_comision_movimiento' => 500]);
        $movimiento = (object) ['id' => 500, 'monto_base' => 30.0, 'monto_comision' => 0.6];
        $ajustes = [];

        $svc = $this->makeService([
            'obtenerRedencionesPendientes' => fn () => collect([$redencion]),
            'obtenerGiftCard' => fn () => $card,
            'obtenerComisionMovimiento' => fn () => $movimiento,
            'ajustarComisionPorAnulacion' => function (object $mov, float $monto, bool $completa, $fecha) use (&$ajustes) {
                $ajustes[] = compact('mov', 'monto', 'completa');

                return new stdClass();
            },
        ]);

        $venta = (object) ['id' => 50, 'id_empresa' => 1, 'fecha' => '2026-07-20'];
        $svc->revertirPorAnulacion($venta);

        $this->assertCount(1, $ajustes);
        $this->assertSame(500, $ajustes[0]['mov']->id);
        $this->assertSame(30.0, $ajustes[0]['monto']);
        $this->assertTrue($ajustes[0]['completa']);
    }

    public function test_sync_por_devolucion_total_restaura_redencion(): void
    {
        $card = $this->makeCard();
        $redencion = $this->makeRedencion();
        $revertCalls = 0;

        $svc = $this->makeService([
            'obtenerRedencionesPendientes' => function () use (&$revertCalls, $redencion) {
                $revertCalls++;

                return collect([$redencion]);
            },
            'obtenerGiftCard' => fn () => $card,
            'obtenerVenta' => fn () => (object) ['id' => 50, 'id_empresa' => 1, 'total' => 100.0],
        ]);

        $devolucion = (object) [
            'id' => 9,
            'id_venta' => 50,
            'tipo' => 'nota_credito',
            'enable' => true,
            'total' => 100.0,
        ];

        $svc->syncPorDevolucion($devolucion);

        $this->assertSame(1, $revertCalls);
        $this->assertNotNull($redencion->reversed_at);
    }

    public function test_sync_por_devolucion_parcial_no_op(): void
    {
        $revertCalls = 0;

        $svc = $this->makeService([
            'obtenerRedencionesPendientes' => function () use (&$revertCalls) {
                $revertCalls++;

                return collect([]);
            },
            'obtenerVenta' => fn () => (object) ['id' => 50, 'id_empresa' => 1, 'total' => 100.0],
        ]);

        $devolucion = (object) [
            'id' => 9,
            'id_venta' => 50,
            'tipo' => 'nota_credito',
            'enable' => true,
            'total' => 40.0,
        ];

        $svc->syncPorDevolucion($devolucion);

        $this->assertSame(0, $revertCalls);
    }
}
