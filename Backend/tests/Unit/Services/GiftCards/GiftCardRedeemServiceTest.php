<?php

namespace Tests\Unit\Services\GiftCards;

use App\Models\GiftCards\GiftCard;
use App\Services\GiftCards\GiftCardRedeemService;
use DomainException;
use PHPUnit\Framework\TestCase;
use stdClass;

class GiftCardRedeemServiceTest extends TestCase
{
    private function makeVenta(): object
    {
        $producto = (object) ['id_categoria' => 10, 'subcategoria_id' => null];
        $detalle = (object) [
            'id' => 100,
            'gravada' => 50.0,
            'exenta' => 0.0,
            'no_sujeta' => 0.0,
            'total' => 56.5,
            'id_vendedor' => 5,
            'producto' => $producto,
        ];

        return (object) [
            'id' => 50,
            'id_empresa' => 1,
            'id_vendedor' => 3,
            'total' => 100.0,
            'fecha_pago' => '2026-07-15',
            'detalles' => [$detalle],
        ];
    }

    private function makeCard(float $saldo): object
    {
        return (object) [
            'id' => 1,
            'id_empresa' => 1,
            'codigo' => 'GC0000000001',
            'saldo' => $saldo,
            'estado' => GiftCard::ESTADO_ACTIVA,
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeService(array $overrides = []): GiftCardRedeemService
    {
        $card = $this->makeCard(100.0);

        $defaults = [
            'buscarGiftCard' => fn () => $card,
            'persistirGiftCard' => function (object $c) {
                return $c;
            },
            'crearRedencion' => fn (array $data) => (object) array_merge(['id' => 200], $data),
            'tieneFuncionalidad' => fn () => false,
            'registrarComisionRedencion' => fn () => null,
        ];

        $config = array_merge($defaults, $overrides);

        return new GiftCardRedeemService(
            $config['buscarGiftCard'],
            $config['persistirGiftCard'],
            $config['crearRedencion'],
            $config['tieneFuncionalidad'],
            $config['registrarComisionRedencion'],
        );
    }

    public function test_saldo_insuficiente(): void
    {
        $svc = $this->makeService([
            'buscarGiftCard' => fn () => $this->makeCard(10.0),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Saldo insuficiente');

        $svc->redeem($this->makeVenta(), 'GC0000000001', 15.0, 5);
    }

    public function test_parcial_deja_activa(): void
    {
        $card = $this->makeCard(100.0);
        $saved = null;

        $svc = $this->makeService([
            'buscarGiftCard' => fn () => $card,
            'persistirGiftCard' => function (object $c) use (&$saved) {
                $saved = $c;

                return $c;
            },
        ]);

        $redencion = $svc->redeem($this->makeVenta(), 'GC0000000001', 40.0, 5);

        $this->assertSame(60.0, (float) $saved->saldo);
        $this->assertSame(GiftCard::ESTADO_ACTIVA, $saved->estado);
        $this->assertSame(60.0, (float) $redencion->saldo_resultante);
    }

    public function test_exacto_deja_agotada(): void
    {
        $card = $this->makeCard(50.0);
        $saved = null;

        $svc = $this->makeService([
            'buscarGiftCard' => fn () => $card,
            'persistirGiftCard' => function (object $c) use (&$saved) {
                $saved = $c;

                return $c;
            },
        ]);

        $redencion = $svc->redeem($this->makeVenta(), 'GC0000000001', 50.0, 5);

        $this->assertSame(0.0, (float) $saved->saldo);
        $this->assertSame(GiftCard::ESTADO_AGOTADA, $saved->estado);
        $this->assertSame(0.0, (float) $redencion->saldo_resultante);
    }

    public function test_comisiones_on_llama_registrar_desde_redencion(): void
    {
        $calls = 0;

        $svc = $this->makeService([
            'tieneFuncionalidad' => fn (int $idEmpresa, string $slug) => $slug === 'comisiones-vendedores',
            'registrarComisionRedencion' => function () use (&$calls) {
                $calls++;

                return (object) ['id' => 99];
            },
            'crearRedencion' => function (array $data) {
                $redencion = (object) array_merge(['id' => 200], $data);

                return $redencion;
            },
        ]);

        $redencion = $svc->redeem($this->makeVenta(), 'GC0000000001', 40.0, 5);

        $this->assertSame(1, $calls);
        $this->assertSame(99, $redencion->id_comision_movimiento);
    }

    public function test_comisiones_off_no_llama_registrar_desde_redencion(): void
    {
        $calls = 0;

        $svc = $this->makeService([
            'tieneFuncionalidad' => fn () => false,
            'registrarComisionRedencion' => function () use (&$calls) {
                $calls++;

                return (object) ['id' => 99];
            },
        ]);

        $redencion = $svc->redeem($this->makeVenta(), 'GC0000000001', 40.0, 5);

        $this->assertSame(0, $calls);
        $this->assertNull($redencion->id_comision_movimiento);
    }
}
