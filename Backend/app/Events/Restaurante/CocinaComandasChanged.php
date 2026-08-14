<?php

namespace App\Events\Restaurante;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Hint de UI para cocina/barra. No es fuente de verdad: el cliente debe GET /comandas.
 */
class CocinaComandasChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public int $idEmpresa,
        public ?int $comandaId = null,
        public ?string $destino = null,
        public ?string $estado = null,
        public string $reason = 'cocina',
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurante.empresa.'.$this->idEmpresa),
        ];
    }

    public function broadcastAs(): string
    {
        return 'cocina.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id_empresa' => $this->idEmpresa,
            'comanda_id' => $this->comandaId,
            'destino' => $this->destino,
            'estado' => $this->estado,
            'reason' => $this->reason,
        ];
    }
}
