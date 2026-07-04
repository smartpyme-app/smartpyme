<?php

namespace App\Http\Resources;

use App\Services\Audit\AuditPresentationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $newValues = is_array($this->new_values) ? $this->new_values : [];

        return [
            'id' => $this->id,
            'descripcion' => app(AuditPresentationService::class)->describe(
                $this->event,
                $this->auditable_type,
                $newValues,
                $this->user?->name
            ),
            'event' => $this->event,
            'module' => $this->module,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'id_empresa' => $this->id_empresa,
            'empresa_nombre' => $this->empresa?->nombre,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
