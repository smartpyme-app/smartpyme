<?php

namespace App\Events\Restaurante;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Hint de UI para mapa de mesas. No es fuente de verdad: el cliente debe GET /mesas.
 */
class MapaMesasChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** Broadcast solo tras commit exitoso de la TX. */
    public bool $afterCommit = true;

    public function __construct(
        public int $idEmpresa,
        public ?int $mesaId = null,
        public ?string $estado = null,
        public ?int $sesionId = null,
        public string $reason = 'mapa',
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
        return 'mapa.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id_empresa' => $this->idEmpresa,
            'mesa_id' => $this->mesaId,
            'estado' => $this->estado,
            'sesion_id' => $this->sesionId,
            'reason' => $this->reason,
        ];
    }
}
